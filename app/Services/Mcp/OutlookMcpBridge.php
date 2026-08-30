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

        $envVars = [];
        if ($this->getClientId()) {
            $envVars['MICROSOFT_CLIENT_ID'] = $this->getClientId();
        }
        if ($this->getClientSecret()) {
            $envVars['MICROSOFT_CLIENT_SECRET'] = $this->getClientSecret();
        }
        if ($this->getTenantId()) {
            $envVars['MICROSOFT_TENANT_ID'] = $this->getTenantId();
        }

        $process = new Process($fullCmd, null, $envVars);
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

    public function getClientId(): ?string
    {
        return $this->user?->microsoft_client_id
            ?: (string) Config::get('services.outlook_mcp.client_id') ?: null;
    }

    public function getClientSecret(): ?string
    {
        return $this->user?->microsoft_client_secret
            ?: (string) Config::get('services.outlook_mcp.client_secret') ?: null;
    }

    public function getTenantId(): ?string
    {
        return $this->user?->microsoft_tenant_id
            ?: (string) Config::get('services.outlook_mcp.tenant_id') ?: null;
    }

    /**
     * Diagnostic probe to test Microsoft Graph credentials with exact error interception.
     *
     * @return array{success: bool, status: string, latency_ms: int, error_code: ?string, error_message: ?string, remediation: ?string}
     */
    public function probeOutlookCredentials(?string $clientId, ?string $clientSecret, ?string $tenantId): array
    {
        $clientId = trim((string) $clientId);
        $clientSecret = trim((string) $clientSecret);
        $tenantId = trim((string) $tenantId);

        if (empty($clientId) && empty($clientSecret) && empty($tenantId)) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'latency_ms' => 0,
                'error_code' => 'UNCONFIGURED',
                'error_message' => 'No Microsoft 365 credentials provided.',
                'remediation' => 'Register an App in Microsoft Entra Admin Center (entra.microsoft.com) and paste the Client ID, Secret, and Tenant ID.',
            ];
        }

        // UUID format validation
        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (! empty($clientId) && ! preg_match($uuidRegex, $clientId)) {
            return [
                'success' => false,
                'status' => 'invalid_format',
                'latency_ms' => 0,
                'error_code' => 'INVALID_CLIENT_ID_FORMAT',
                'error_message' => 'Client Error: Microsoft Client ID must be a valid 36-character UUID (e.g., 12345678-abcd-ef01-2345-6789abcdef01).',
                'remediation' => 'Copy the Application (client) ID from Azure Portal -> App registrations -> Overview.',
            ];
        }

        if (! empty($tenantId) && ! in_array(strtolower($tenantId), ['common', 'organizations', 'consumers'], true) && ! preg_match($uuidRegex, $tenantId)) {
            return [
                'success' => false,
                'status' => 'invalid_format',
                'latency_ms' => 0,
                'error_code' => 'INVALID_TENANT_ID_FORMAT',
                'error_message' => 'Client Error: Microsoft Tenant ID must be a valid 36-character UUID or "organizations".',
                'remediation' => 'Copy the Directory (tenant) ID from Azure Portal -> App registrations -> Overview.',
            ];
        }

        if (empty($clientSecret)) {
            return [
                'success' => false,
                'status' => 'missing_secret',
                'latency_ms' => 0,
                'error_code' => 'MISSING_CLIENT_SECRET',
                'error_message' => 'Client Error: Microsoft Client Secret is required.',
                'remediation' => 'In Azure Portal, navigate to Certificates & secrets -> New client secret, and copy the Value column.',
            ];
        }

        // In test/mock environment, validate syntax and return successful probe
        if (app()->environment('testing')) {
            return [
                'success' => true,
                'status' => 'connected',
                'latency_ms' => 45,
                'error_code' => null,
                'error_message' => null,
                'remediation' => null,
            ];
        }

        // Live probe against Microsoft Entra token endpoint
        $startTime = microtime(true);
        try {
            $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->timeout(8)
                ->post($tokenUrl, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'connected',
                    'latency_ms' => $latencyMs,
                    'error_code' => null,
                    'error_message' => null,
                    'remediation' => null,
                ];
            }

            $body = $response->json() ?? [];
            $errorDesc = (string) ($body['error_description'] ?? $response->body());
            $errorCode = (string) ($body['error'] ?? 'OAUTH_ERROR');

            $remediation = 'Check your Application ID, Secret, and Tenant ID in Microsoft Entra Admin Center.';
            if (str_contains($errorDesc, 'AADSTS7000215')) {
                $remediation = 'Invalid or expired client secret. Generate a new Client Secret under Certificates & secrets in Azure Portal and copy the Value.';
            } elseif (str_contains($errorDesc, 'AADSTS700016')) {
                $remediation = 'Application not found in tenant. Verify that the Client ID and Tenant ID match the App Registration in Azure Portal.';
            }

            return [
                'success' => false,
                'status' => 'auth_failed',
                'latency_ms' => $latencyMs,
                'error_code' => $errorCode,
                'error_message' => "HTTP {$response->status()}: {$errorDesc}",
                'remediation' => $remediation,
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'status' => 'network_error',
                'latency_ms' => $latencyMs,
                'error_code' => 'CONNECTION_FAILED',
                'error_message' => 'Network / Connection error: '.$e->getMessage(),
                'remediation' => 'Ensure the server has outbound internet connectivity to login.microsoftonline.com.',
            ];
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
