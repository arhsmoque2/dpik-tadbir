<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use IMAP\Connection;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Replaces the former OutlookMcpBridge (Microsoft Graph via a Python
 * subprocess — see issue #40 / ADR-003). Every DPIK mailbox lives on the
 * same company mail server (mail.dpik.com.my) with the same IMAP/SMTP
 * settings, differing only by the executive's own email + password — no
 * Microsoft Entra app registration required. Retrieval goes over IMAP,
 * outbound actions (draft/reply/forward) go over SMTP, both authenticated
 * with the executive's own sovereign per-user credentials
 * (User::$imap_username/$imap_password, $smtp_host/$smtp_port/$smtp_password).
 */
class MailBridge
{
    protected ?User $user = null;

    public function forUser(User $user): self
    {
        $clone = clone $this;
        $clone->user = $user;

        return $clone;
    }

    public function checkAuthStatus(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        try {
            $conn = $this->openImapConnection();
            imap_close($conn);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchInboxDelta(int $lookbackHours = 24, int $limit = 25, bool $concise = true): array
    {
        if (app()->environment('testing')) {
            return $this->mockInboxDelta();
        }

        try {
            $conn = $this->openImapConnection();
            $since = now()->subHours(max($lookbackHours, 1))->format('d-M-Y');
            $uids = imap_search($conn, 'SINCE "'.$since.'"', SE_UID) ?: [];
            rsort($uids);
            $uids = array_slice($uids, 0, $limit);

            $messages = array_map(fn (int $uid) => $this->summarizeMessage($conn, $uid, $concise), $uids);
            imap_close($conn);

            return ['messages' => $messages];
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function searchMail(string $query, int $limit = 25, bool $concise = true): array
    {
        if (app()->environment('testing')) {
            return ['messages' => []];
        }

        try {
            $conn = $this->openImapConnection();
            $criteria = 'TEXT "'.addcslashes($query, '"\\').'"';
            $uids = imap_search($conn, $criteria, SE_UID) ?: [];
            rsort($uids);
            $uids = array_slice($uids, 0, $limit);

            $messages = array_map(fn (int $uid) => $this->summarizeMessage($conn, $uid, $concise), $uids);
            imap_close($conn);

            return ['messages' => $messages];
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function readMessage(string $messageId, bool $concise = true): array
    {
        if (app()->environment('testing')) {
            return [
                'id' => $messageId,
                'subject' => 'Projek Bekalan Air Mukah',
                'body' => 'Laporan teknikal prelim siap untuk semakan Pengarah.',
                'from' => 'engineer@dpik.com.my',
            ];
        }

        try {
            $conn = $this->openImapConnection();
            $uid = (int) $messageId;
            $msgno = imap_msgno($conn, $uid);
            $overview = ($msgno ? (imap_fetch_overview($conn, (string) $msgno, 0)[0] ?? null) : null);
            $rawBody = (string) imap_body($conn, $uid, FT_UID);
            $body = trim(strip_tags($rawBody));
            imap_close($conn);

            return [
                'id' => $messageId,
                'subject' => $overview ? imap_utf8((string) ($overview->subject ?? '')) : '',
                'from' => $overview->from ?? '',
                'body' => $concise ? Str::limit($body, 4000) : $body,
            ];
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * Stages a draft by appending it directly to the mailbox's Drafts
     * folder over IMAP — the mailbox-native equivalent of a Graph API
     * "create draft" call, no Microsoft account required.
     *
     * @param  list<string>  $toRecipients
     * @param  list<string>  $ccRecipients
     * @return array<string, mixed>
     */
    public function createDraft(string $subject, string $body, array $toRecipients, array $ccRecipients = []): array
    {
        if (app()->environment('testing')) {
            return [
                'id' => 'draft_'.uniqid(),
                'status' => 'draft_created',
                'subject' => $subject,
            ];
        }

        try {
            $email = (new Email)
                ->from($this->fromAddress())
                ->subject($subject)
                ->text($body);

            foreach ($toRecipients ?: [$this->fromAddress()] as $to) {
                $email->addTo($to);
            }
            foreach ($ccRecipients as $cc) {
                $email->addCc($cc);
            }

            $conn = $this->openImapConnection($this->draftsFolder());
            $appended = imap_append($conn, $this->mailboxSpec($this->draftsFolder()), str_replace("\n", "\r\n", $email->toString()), '\\Draft');
            imap_close($conn);

            return [
                'id' => 'draft_'.uniqid(),
                'status' => $appended ? 'draft_created' : 'failed',
                'subject' => $subject,
            ];
        } catch (Throwable $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * @param  list<string>  $attachments
     */
    public function sendReply(string $messageId, string $body, array $attachments = []): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        try {
            $original = $this->readMessage($messageId, concise: false);
            $replyTo = $this->extractEmailAddress((string) ($original['from'] ?? ''));

            $email = (new Email)
                ->from($this->fromAddress())
                ->to($replyTo ?: $this->fromAddress())
                ->subject('Re: '.($original['subject'] ?? ''))
                ->text($body);

            $this->mailer()->send($email);

            return true;
        } catch (Throwable $e) {
            Log::warning('Mail bridge reply failed', ['message_id' => $messageId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  list<string>  $toRecipients
     */
    public function forwardMessage(string $messageId, array $toRecipients, string $comment = ''): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        try {
            $original = $this->readMessage($messageId, concise: false);
            $text = trim($comment."\n\n---------- Forwarded message ----------\n".($original['body'] ?? ''));

            $email = (new Email)
                ->from($this->fromAddress())
                ->subject('Fwd: '.($original['subject'] ?? ''))
                ->text($text);

            foreach ($toRecipients as $to) {
                $email->addTo($to);
            }

            $this->mailer()->send($email);

            return true;
        } catch (Throwable $e) {
            Log::warning('Mail bridge forward failed', ['message_id' => $messageId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mockInboxDelta(): array
    {
        return [
            'messages' => [
                [
                    'id' => 'msg_001',
                    'subject' => 'Mesyuarat Kemajuan Projek FT264 Sri Aman',
                    'from' => 'jkr_sarawak@jkr.gov.my',
                    'received_at' => now()->subHours(2)->toIso8601String(),
                    'snippet' => 'Sila sahkan kehadiran ke mesyuarat tapak minggu depan.',
                ],
            ],
        ];
    }

    /**
     * @param  Connection  $conn
     * @return array<string, mixed>
     */
    protected function summarizeMessage($conn, int $uid, bool $concise): array
    {
        $msgno = imap_msgno($conn, $uid);
        $overview = $msgno ? (imap_fetch_overview($conn, (string) $msgno, 0)[0] ?? null) : null;
        $subject = $overview ? imap_utf8((string) ($overview->subject ?? '')) : '';
        $date = $overview->date ?? null;

        $entry = [
            'id' => (string) $uid,
            'subject' => $subject,
            'from' => $overview->from ?? '',
            'received_at' => $date ? Carbon::parse($date)->toIso8601String() : null,
        ];

        if (! $concise) {
            $body = (string) imap_fetchbody($conn, $uid, '1', FT_UID);
            $entry['snippet'] = Str::limit(trim(strip_tags($body)), 280);
        } else {
            $entry['snippet'] = Str::limit($subject, 140);
        }

        return $entry;
    }

    protected function extractEmailAddress(string $fromHeader): ?string
    {
        if (preg_match('/<([^>]+)>/', $fromHeader, $m)) {
            return $m[1];
        }

        return filled($fromHeader) ? trim($fromHeader) : null;
    }

    protected function fromAddress(): string
    {
        return $this->imapUsername() ?: (string) Config::get('services.company_mail.default_from', 'no-reply@dpik.com.my');
    }

    protected function imapUsername(): ?string
    {
        return $this->user?->imap_username ?: $this->user?->email;
    }

    protected function draftsFolder(): string
    {
        return (string) Config::get('services.company_mail.drafts_folder', 'INBOX.Drafts');
    }

    protected function mailboxSpec(string $folder = 'INBOX'): string
    {
        $host = $this->user?->imap_host ?: Config::get('services.company_mail.host', 'mail.dpik.com.my');
        $port = $this->user?->imap_port ?: Config::get('services.company_mail.imap_port', 993);

        return "{{$host}:{$port}/imap/ssl/novalidate-cert}{$folder}";
    }

    /**
     * @return Connection
     */
    protected function openImapConnection(string $folder = 'INBOX')
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('IMAP mail bridge is unavailable.');
        }

        $username = $this->imapUsername();
        $password = $this->user?->imap_password;

        if (! $username || ! $password) {
            throw new RuntimeException('Mail bridge is not configured for this executive.');
        }

        $timeout = (int) Config::get('services.company_mail.timeout', 30);
        imap_timeout(IMAP_OPENTIMEOUT, $timeout);

        $conn = @imap_open($this->mailboxSpec($folder), $username, $password, 0, 1);

        if (! $conn) {
            throw new RuntimeException('IMAP mail bridge is unavailable: '.imap_last_error());
        }

        return $conn;
    }

    protected function mailer(): Mailer
    {
        $host = $this->user?->smtp_host ?: Config::get('services.company_mail.host', 'mail.dpik.com.my');
        $port = (int) ($this->user?->smtp_port ?: Config::get('services.company_mail.smtp_port', 465));
        $username = $this->imapUsername();
        $password = $this->user?->smtp_password ?: $this->user?->imap_password;

        if (! $username || ! $password) {
            throw new RuntimeException('Mail bridge is not configured for this executive.');
        }

        $transport = new EsmtpTransport($host, $port, $port === 465);
        $transport->setUsername($username);
        $transport->setPassword($password);

        return new Mailer($transport);
    }

    /**
     * Strips raw exception/stack-trace signatures before an error reaches
     * the executive-facing chat transcript (see ADR / issue #41's original
     * "sh: 1: exec: uv: not found" leak — the same principle applies to any
     * backend error, not just the Python subprocess it was written for).
     *
     * @return array<string, mixed>
     */
    protected function unavailable(Throwable $e): array
    {
        Log::warning('Mail bridge error', ['error' => $e->getMessage()]);

        return ['status' => 'unavailable', 'error' => $this->sanitizeUserFacingError($e->getMessage())];
    }

    protected function sanitizeUserFacingError(string $message): string
    {
        $rawSignatures = [
            '/Fatal error:/i',
            '/Stack trace:/i',
            '/\.php on line \d+/i',
            '/Warning:/i',
            '/imap_\w+\(\):/i',
            '/Traceback \(most recent call last\)/',
        ];

        foreach ($rawSignatures as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return 'Mail bridge is unavailable.';
            }
        }

        return Str::limit($message, 200);
    }
}
