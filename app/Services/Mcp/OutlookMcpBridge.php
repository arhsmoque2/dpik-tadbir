<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Settings\OutlookSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class OutlookMcpBridge
{
    protected ?User $user = null;

    public function forUser(User $user): self
    {
        $clone = clone $this;
        $clone->user = $user;

        return $clone;
    }

    /**
     * Executes a tool against the Outlook MCP server.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        // In local/testing mode without running external daemon, return mock structures if configured
        if (app()->environment('testing')) {
            return $this->mockToolResponse($toolName, $arguments);
        }

        $command = (string) Config::get('services.outlook_mcp.command', 'uv');
        $args = (string) Config::get('services.outlook_mcp.args', 'run python -m outlook_mcp.server');
        $timeout = (int) Config::get('services.outlook_mcp.timeout', 30);

        try {
            if (class_exists(OutlookSettings::class)) {
                /** @var OutlookSettings $settings */
                $settings = app(OutlookSettings::class);
                if (! empty($settings->mcp_command)) {
                    $command = $settings->mcp_command;
                }
                if (! empty($settings->mcp_args)) {
                    $args = $settings->mcp_args;
                }
                if ($settings->timeout_seconds > 0) {
                    $timeout = $settings->timeout_seconds;
                }
            }
        } catch (\Throwable $e) {
            // fallback to config values
        }

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => uniqid('mcp_', true),
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $arguments,
            ],
        ], JSON_THROW_ON_ERROR);

        $fullCmd = array_merge([$command], explode(' ', $args));

        $process = new Process($fullCmd);
        $process->setInput($payload."\n");
        $process->setTimeout($timeout);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                Log::error('Outlook MCP bridge execution failed', [
                    'tool' => $toolName,
                    'error' => $process->getErrorOutput(),
                ]);
                throw new RuntimeException('Outlook MCP bridge error: '.$process->getErrorOutput());
            }

            $output = trim($process->getOutput());
            $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($response) && isset($response['result'])) {
                /** @var array<string, mixed> $result */
                $result = $response['result'];

                return $result;
            }

            return ['raw_output' => $output];
        } catch (\Throwable $e) {
            Log::warning('Outlook MCP connection error fallback', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'unavailable', 'error' => $e->getMessage()];
        }
    }

    public function checkAuthStatus(): bool
    {
        $res = $this->callTool('outlook_auth_status');

        return ($res['authenticated'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchInboxDelta(int $lookbackHours = 24, int $limit = 25, bool $concise = true): array
    {
        return $this->callTool('outlook_list_inbox_delta', [
            'lookback_hours' => $lookbackHours,
            'limit' => $limit,
            'concise' => $concise,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function searchMail(string $query, int $limit = 25, bool $concise = true): array
    {
        return $this->callTool('outlook_search_mail', [
            'query' => $query,
            'limit' => $limit,
            'concise' => $concise,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function readMessage(string $messageId, bool $concise = true): array
    {
        return $this->callTool('outlook_read_message', [
            'message_id' => $messageId,
            'concise' => $concise,
        ]);
    }

    /**
     * @param  list<string>  $toRecipients
     * @param  list<string>  $ccRecipients
     * @return array<string, mixed>
     */
    public function createDraft(string $subject, string $body, array $toRecipients, array $ccRecipients = []): array
    {
        return $this->callTool('outlook_create_draft', [
            'subject' => $subject,
            'body' => $body,
            'to_recipients' => $toRecipients,
            'cc_recipients' => $ccRecipients,
        ]);
    }

    /**
     * @param  list<string>  $attachments
     */
    public function sendReply(string $messageId, string $body, array $attachments = []): bool
    {
        $res = $this->callTool('outlook_reply', [
            'message_id' => $messageId,
            'body' => $body,
            'attachments' => $attachments,
        ]);

        return ($res['status'] ?? '') === 'sent' || ($res['success'] ?? false) === true;
    }

    /**
     * @param  list<string>  $toRecipients
     */
    public function forwardMessage(string $messageId, array $toRecipients, string $comment = ''): bool
    {
        $res = $this->callTool('outlook_forward', [
            'message_id' => $messageId,
            'to_recipients' => $toRecipients,
            'comment' => $comment,
        ]);

        return ($res['status'] ?? '') === 'forwarded' || ($res['success'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function mockToolResponse(string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'outlook_auth_status' => ['authenticated' => true, 'user' => $this->user !== null ? $this->user->email : 'test@dpik.com.my'],
            'outlook_list_inbox_delta' => [
                'messages' => [
                    [
                        'id' => 'msg_001',
                        'subject' => 'Mesyuarat Kemajuan Projek FT264 Sri Aman',
                        'from' => 'jkr_sarawak@jkr.gov.my',
                        'received_at' => now()->subHours(2)->toIso8601String(),
                        'snippet' => 'Sila sahkan kehadiran ke mesyuarat tapak minggu depan.',
                    ],
                ],
            ],
            'outlook_search_mail' => ['messages' => []],
            'outlook_read_message' => [
                'id' => $arguments['message_id'] ?? 'msg_001',
                'subject' => 'Projek Bekalan Air Mukah',
                'body' => 'Laporan teknikal prelim siap untuk semakan Pengarah.',
                'from' => 'engineer@dpik.com.my',
            ],
            'outlook_create_draft' => [
                'id' => 'draft_'.uniqid(),
                'status' => 'draft_created',
                'subject' => $arguments['subject'] ?? '',
            ],
            'outlook_reply' => ['status' => 'sent', 'success' => true],
            'outlook_forward' => ['status' => 'forwarded', 'success' => true],
            default => ['status' => 'ok'],
        };
    }
}
