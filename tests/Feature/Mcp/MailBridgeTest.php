<?php

/**
 * Hermetic tests for the real (non-testing-env) MailBridge code paths,
 * following this repo's established namespace-function-interception
 * pattern (ADR-029 / tests/Unit/MailDiagnosticServiceTest.php) — the
 * ext-imap functions MailBridge calls unqualified inside App\Services\Mail
 * are intercepted here via PHP's namespace fallback resolution, so no real
 * IMAP/SMTP server or a locally-loaded ext-imap extension is required.
 */

namespace Tests\Feature\Mcp;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class ImapFakeRegistry
{
    public static bool $openSucceeds = true;

    public static string $lastError = 'Mailbox not found, or bad credentials';

    /** @var list<array{uid: int, subject: string, from: string, date: string, body: string}> */
    public static array $messages = [];

    public static bool $appendSucceeds = true;

    /** @var list<string> */
    public static array $appendedRawMessages = [];

    public static function reset(): void
    {
        self::$openSucceeds = true;
        self::$lastError = 'Mailbox not found, or bad credentials';
        self::$messages = [];
        self::$appendSucceeds = true;
        self::$appendedRawMessages = [];
    }

    /**
     * @param  array{uid?: int, subject?: string, from?: string, date?: string, body?: string}  $overrides
     */
    public static function addMessage(array $overrides = []): void
    {
        self::$messages[] = array_merge([
            'uid' => 501,
            'subject' => 'Mesyuarat Kemajuan Projek FT264 Sri Aman',
            'from' => 'jkr_sarawak@jkr.gov.my',
            'date' => 'Wed, 2 Sep 2026 09:00:00 +0800',
            'body' => '<p>Sila sahkan kehadiran ke mesyuarat tapak minggu depan.</p>',
        ], $overrides);
    }

    /**
     * @return array{uid: int, subject: string, from: string, date: string, body: string}|null
     */
    public static function findByUid(int $uid): ?array
    {
        foreach (self::$messages as $message) {
            if ($message['uid'] === $uid) {
                return $message;
            }
        }

        return null;
    }
}

/**
 * A no-op Symfony Mailer transport — records what MailBridge tried to send
 * without ever touching a real socket.
 */
class RecordingTransport implements TransportInterface
{
    /** @var list<RawMessage> */
    public static array $sent = [];

    public static bool $throws = false;

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (self::$throws) {
            throw new \RuntimeException('SMTP AUTH LOGIN failed: 535 Incorrect authentication data');
        }

        self::$sent[] = $message;

        return null;
    }

    public function __toString(): string
    {
        return 'recording://fake';
    }

    public static function reset(): void
    {
        self::$sent = [];
        self::$throws = false;
    }
}

namespace App\Services\Mail;

use Tests\Feature\Mcp\ImapFakeRegistry;

if (! function_exists('App\Services\Mail\imap_open')) {
    /**
     * @return object|false
     */
    function imap_open(string $mailbox, string $user, string $password, int $options = 0, int $retries = 0)
    {
        return ImapFakeRegistry::$openSucceeds ? new \stdClass : false;
    }

    function imap_close($connection): bool
    {
        return true;
    }

    /**
     * @return list<int>|false
     */
    function imap_search($connection, string $criteria, int $flags = 0)
    {
        $uids = array_column(ImapFakeRegistry::$messages, 'uid');

        return $uids ?: false;
    }

    function imap_msgno($connection, int $uid): int
    {
        return $uid;
    }

    /**
     * @return list<object>
     */
    function imap_fetch_overview($connection, string $sequence, int $flags = 0): array
    {
        $message = ImapFakeRegistry::findByUid((int) $sequence);
        if ($message === null) {
            return [];
        }

        $overview = new \stdClass;
        $overview->subject = $message['subject'];
        $overview->from = $message['from'];
        $overview->date = $message['date'];

        return [$overview];
    }

    function imap_fetchbody($connection, int $uid, string $section, int $flags = 0): string
    {
        return ImapFakeRegistry::findByUid($uid)['body'] ?? '';
    }

    function imap_body($connection, int $uid, int $flags = 0): string
    {
        return ImapFakeRegistry::findByUid($uid)['body'] ?? '';
    }

    function imap_append($connection, string $mailbox, string $message, ?string $options = null): bool
    {
        ImapFakeRegistry::$appendedRawMessages[] = $message;

        return ImapFakeRegistry::$appendSucceeds;
    }

    function imap_utf8(string $text): string
    {
        return $text;
    }

    function imap_last_error(): string
    {
        return ImapFakeRegistry::$lastError;
    }

    function imap_timeout(int $type, int $timeout = -1): bool
    {
        return true;
    }
}

namespace Tests\Feature\Mcp;

use App\Models\User;
use App\Services\Mail\MailBridge;
use Symfony\Component\Mailer\Mailer;

/**
 * Test double that overrides MailBridge's real transport with
 * RecordingTransport, so sendReply()/forwardMessage() are fully exercised
 * without a real SMTP connection.
 */
class RecordingMailBridge extends MailBridge
{
    protected function mailer(): Mailer
    {
        return new Mailer(new RecordingTransport);
    }
}

beforeEach(function () {
    ImapFakeRegistry::reset();
    RecordingTransport::reset();

    $this->user = User::create([
        'name' => 'Abdul Rahman Hilmi',
        'email' => 'abdulrahman@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
        'imap_host' => 'mail.dpik.com.my',
        'imap_port' => 993,
        'imap_username' => 'abdulrahman@dpik.com.my',
        'imap_password' => 'correct-mailbox-password',
        'smtp_host' => 'mail.dpik.com.my',
        'smtp_port' => 465,
        'smtp_password' => 'correct-mailbox-password',
    ]);
});

afterEach(function () {
    ImapFakeRegistry::reset();
    RecordingTransport::reset();
});

test('bridge executes tools and returns mock responses in the testing environment', function () {
    $bridge = app(MailBridge::class)->forUser($this->user);

    $auth = $bridge->checkAuthStatus();
    expect($auth)->toBeTrue();

    $delta = $bridge->fetchInboxDelta(lookbackHours: 12, limit: 10);
    expect($delta)->toHaveKey('messages');

    $search = $bridge->searchMail('FT264');
    expect($search)->toHaveKey('messages');

    $read = $bridge->readMessage('msg_001');
    expect($read)->toHaveKey('subject');

    $draft = $bridge->createDraft(
        subject: 'Draft Subject',
        body: 'Draft content',
        toRecipients: ['client@domain.com']
    );
    expect($draft)->toHaveKey('status', 'draft_created');

    $reply = $bridge->sendReply('msg_001', 'Reply body');
    expect($reply)->toBeTrue();

    $forward = $bridge->forwardMessage('msg_001', ['director@dpik.com.my'], 'FYI');
    expect($forward)->toBeTrue();
});

test('bridge fails closed when the executive has no mailbox credentials configured', function () {
    // No imap_username/imap_password on the user and no fallback in config —
    // this is the real, un-mocked path (production mode), unlike the tests
    // above which run under the testing-env mock shortcut.
    app()['env'] = 'production';

    $user = User::create([
        'name' => 'Unconfigured Exec',
        'email' => 'unconfigured@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $bridge = app(MailBridge::class)->forUser($user);
    $res = $bridge->fetchInboxDelta();

    expect($res)->toHaveKey('status', 'unavailable');
    expect($res['error'])->toBeString();

    app()['env'] = 'testing';
});

// @capability(chat.no-raw-backend-errors)
test('bridge never leaks raw IMAP/PHP internals into the executive-facing error', function () {
    // Regression test carried over from the deployed "Outlook MCP bridge
    // error: sh: 1: exec: uv: not found" leak (issue #40 / #41): a mail
    // bridge failure must fail closed with a clean message, not raw
    // exception/stack-trace text, in the chat transcript — regardless of
    // which transport (subprocess, then; IMAP/SMTP, now) is behind it.
    app()['env'] = 'production';
    ImapFakeRegistry::$openSucceeds = false;
    ImapFakeRegistry::$lastError = 'Fatal error: Uncaught RuntimeException in imap.c on line 412';

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->fetchInboxDelta();

    expect($res)->toHaveKey('status', 'unavailable');
    expect($res['error'])
        ->not->toContain('Fatal error:')
        ->not->toContain('Stack trace:')
        ->not->toContain('.php on line')
        ->not->toContain('Traceback');

    app()['env'] = 'testing';
});

test('searchMail and readMessage fail closed like fetchInboxDelta when the mailbox is unreachable', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::$openSucceeds = false;

    $bridge = app(MailBridge::class)->forUser($this->user);

    $search = $bridge->searchMail('FT264');
    expect($search)->toHaveKey('status', 'unavailable');

    $read = $bridge->readMessage('501');
    expect($read)->toHaveKey('status', 'unavailable');

    app()['env'] = 'testing';
});

test('sendReply falls back to the IMAP password when no SMTP password is set', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage(['uid' => 501]);

    $user = User::create([
        'name' => 'No SMTP Exec',
        'email' => 'no_smtp@dpik.com.my',
        'password' => bcrypt('password'),
        'imap_host' => 'mail.dpik.com.my',
        'imap_username' => 'no_smtp@dpik.com.my',
        'imap_password' => 'imap-only-password',
        // No smtp_password — MailBridge::mailer() falls back to imap_password.
    ]);

    $bridge = (new RecordingMailBridge)->forUser($user);

    expect($bridge->sendReply('501', 'Body'))->toBeTrue();
    expect(RecordingTransport::$sent)->toHaveCount(1);

    app()['env'] = 'testing';
});

test('sendReply fails closed when the executive has no mailbox credentials at all', function () {
    // Uses the real MailBridge (not RecordingMailBridge, whose mailer()
    // override would bypass the credential check entirely) — mailer()'s
    // own "not configured" guard must throw before any transport is built.
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage(['uid' => 501]);

    $user = User::create([
        'name' => 'No Credentials Exec',
        'email' => 'no_creds@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $bridge = app(MailBridge::class)->forUser($user);

    expect($bridge->sendReply('501', 'Body'))->toBeFalse();

    app()['env'] = 'testing';
});

test('fetchInboxDelta returns concise and non-concise summaries over the real IMAP path', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage([
        'uid' => 501,
        'subject' => 'Laporan Projek Mukah',
        'from' => 'engineer@dpik.com.my',
        'body' => '<p>Laporan teknikal prelim siap untuk semakan Pengarah.</p>',
    ]);

    $bridge = app(MailBridge::class)->forUser($this->user);

    $concise = $bridge->fetchInboxDelta(lookbackHours: 24, limit: 10, concise: true);
    expect($concise['messages'][0]['id'])->toBe('501')
        ->and($concise['messages'][0]['subject'])->toBe('Laporan Projek Mukah')
        ->and($concise['messages'][0]['snippet'])->toBe('Laporan Projek Mukah');

    $full = $bridge->fetchInboxDelta(lookbackHours: 24, limit: 10, concise: false);
    expect($full['messages'][0]['snippet'])->toContain('Laporan teknikal prelim siap');

    app()['env'] = 'testing';
});

test('fetchInboxDelta returns an empty message list when the mailbox has nothing new', function () {
    app()['env'] = 'production';

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->fetchInboxDelta();

    expect($res)->toBe(['messages' => []]);

    app()['env'] = 'testing';
});

test('searchMail returns matching messages over the real IMAP path', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage(['uid' => 777, 'subject' => 'FT264 Sri Aman update']);

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->searchMail('FT264', limit: 5, concise: true);

    expect($res['messages'])->toHaveCount(1)
        ->and($res['messages'][0]['id'])->toBe('777');

    app()['env'] = 'testing';
});

test('readMessage returns the full body over the real IMAP path', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage([
        'uid' => 501,
        'subject' => 'Laporan Projek Mukah',
        'from' => 'engineer@dpik.com.my',
        'body' => '<p>Laporan teknikal prelim siap.</p>',
    ]);

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->readMessage('501', concise: false);

    expect($res['id'])->toBe('501')
        ->and($res['subject'])->toBe('Laporan Projek Mukah')
        ->and($res['from'])->toBe('engineer@dpik.com.my')
        ->and($res['body'])->toBe('Laporan teknikal prelim siap.');

    app()['env'] = 'testing';
});

test('createDraft appends a MIME message to the Drafts folder over IMAP', function () {
    app()['env'] = 'production';

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->createDraft('Draft Subject', 'Draft body', ['client@domain.com'], ['cc@domain.com']);

    expect($res['status'])->toBe('draft_created');
    expect(ImapFakeRegistry::$appendedRawMessages)->toHaveCount(1);
    expect(ImapFakeRegistry::$appendedRawMessages[0])
        ->toContain('Draft Subject')
        ->toContain('cc@domain.com');

    app()['env'] = 'testing';
});

test('createDraft reports failure when the IMAP append is rejected', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::$appendSucceeds = false;

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->createDraft('Draft Subject', 'Draft body', ['client@domain.com']);

    expect($res['status'])->toBe('failed');

    app()['env'] = 'testing';
});

test('sendReply dispatches over SMTP to the original sender', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage([
        'uid' => 501,
        'subject' => 'Laporan Projek Mukah',
        'from' => 'Engineer <engineer@dpik.com.my>',
        'body' => 'Original body.',
    ]);

    $bridge = (new RecordingMailBridge)->forUser($this->user);
    $ok = $bridge->sendReply('501', 'Confirmed, thank you.');

    expect($ok)->toBeTrue();
    expect(RecordingTransport::$sent)->toHaveCount(1);
    expect(RecordingTransport::$sent[0]->toString())
        ->toContain('Re: Laporan Projek Mukah')
        ->toContain('engineer@dpik.com.my');

    app()['env'] = 'testing';
});

test('sendReply fails closed when the SMTP transport rejects the message', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage(['uid' => 501]);
    RecordingTransport::$throws = true;

    $bridge = (new RecordingMailBridge)->forUser($this->user);
    $ok = $bridge->sendReply('501', 'Body');

    expect($ok)->toBeFalse();

    app()['env'] = 'testing';
});

test('forwardMessage dispatches over SMTP with the comment prepended', function () {
    app()['env'] = 'production';
    ImapFakeRegistry::addMessage([
        'uid' => 501,
        'subject' => 'Laporan Projek Mukah',
        'body' => 'Original body.',
    ]);

    $bridge = (new RecordingMailBridge)->forUser($this->user);
    $ok = $bridge->forwardMessage('501', ['director@dpik.com.my'], 'FYI, please review.');

    expect($ok)->toBeTrue();
    expect(RecordingTransport::$sent)->toHaveCount(1);
    expect(RecordingTransport::$sent[0]->toString())
        ->toContain('Fwd: Laporan Projek Mukah')
        ->toContain('FYI, please review.')
        ->toContain('director@dpik.com.my');

    app()['env'] = 'testing';
});

test('checkAuthStatus reflects the real IMAP connection outcome', function () {
    app()['env'] = 'production';

    $bridge = app(MailBridge::class)->forUser($this->user);
    expect($bridge->checkAuthStatus())->toBeTrue();

    ImapFakeRegistry::$openSucceeds = false;
    expect($bridge->checkAuthStatus())->toBeFalse();

    app()['env'] = 'testing';
});

test('mail bridge provides fluent methods for user', function () {
    $user = User::create([
        'name' => 'MD User 2',
        'email' => 'md2@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $bridge = new MailBridge;
    $bridge->forUser($user);

    expect($bridge->checkAuthStatus())->toBeBool();
    expect($bridge->fetchInboxDelta(24, 10, true))->toBeArray();
    expect($bridge->searchMail('test', 10, true))->toBeArray();
    expect($bridge->readMessage('msg_123', true))->toBeArray();
    expect($bridge->createDraft('Subj', 'Body', ['a@b.com']))->toBeArray();
    expect($bridge->sendReply('msg_123', 'Reply text'))->toBeBool();
    expect($bridge->forwardMessage('msg_123', ['a@b.com'], 'Fwd comment'))->toBeBool();
});
