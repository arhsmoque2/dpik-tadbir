<?php

declare(strict_types=1);

namespace App\Services\Mcp;

class MailDiagnosticService
{
    /**
     * Probes IMAP server connectivity, SSL handshake, and protocol banner.
     *
     * @return array{status: string, latency_ms: int, message: string, remediation: ?string}
     */
    public function probeImap(
        string $host = 'mail.dpik.com.my',
        int $port = 993,
        ?string $username = null,
        ?string $password = null,
        int $timeout = 4
    ): array {
        $startTime = microtime(true);
        $cleanHost = preg_replace('#^ssl://|^tls://#', '', trim($host)) ?: 'mail.dpik.com.my';

        $transport = $port === 993 ? "ssl://{$cleanHost}" : "tcp://{$cleanHost}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "{$transport}:{$port}",
            $errno,
            $errstr,
            (float) $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $socket) {
            return [
                'status' => 'error',
                'latency_ms' => $latencyMs,
                'message' => "Could not connect to IMAP server at {$cleanHost}:{$port} ({$errstr})",
                'remediation' => 'Verify your network allows outbound traffic on port 993 (SSL) or check the hostname.',
            ];
        }

        stream_set_timeout($socket, $timeout);
        $banner = fgets($socket, 1024);

        if ($banner === false || ! str_contains($banner, '* OK')) {
            fclose($socket);

            return [
                'status' => 'error',
                'latency_ms' => $latencyMs,
                'message' => 'IMAP greeting failed or returned unexpected response.',
                'remediation' => 'Check if the IMAP service is running on the mail server.',
            ];
        }

        // If credentials are provided, attempt authentication
        if (filled($username) && filled($password)) {
            $userClean = addcslashes((string) $username, '"\\');
            $passClean = addcslashes((string) $password, '"\\');
            fwrite($socket, "a001 LOGIN \"{$userClean}\" \"{$passClean}\"\r\n");

            $authResponse = '';
            while ($line = fgets($socket, 1024)) {
                $authResponse .= $line;
                if (str_starts_with($line, 'a001 OK') || str_starts_with($line, 'a001 NO') || str_starts_with($line, 'a001 BAD')) {
                    break;
                }
            }

            fwrite($socket, "a002 LOGOUT\r\n");
            fclose($socket);

            if (str_contains($authResponse, 'a001 OK')) {
                return [
                    'status' => 'success',
                    'latency_ms' => $latencyMs,
                    'message' => "IMAP Authenticated successfully as {$username} (Mailbox active)",
                    'remediation' => null,
                ];
            }

            return [
                'status' => 'error',
                'latency_ms' => $latencyMs,
                'message' => "IMAP Connected but authentication failed for {$username}",
                'remediation' => 'Verify your company email password in Executive Settings.',
            ];
        }

        fwrite($socket, "a001 LOGOUT\r\n");
        fclose($socket);

        return [
            'status' => 'success',
            'latency_ms' => $latencyMs,
            'message' => "IMAP Server Reachable on {$cleanHost}:{$port} (SSL Handshake OK)",
            'remediation' => null,
        ];
    }

    /**
     * Probes SMTP server connectivity and TLS banner.
     *
     * @return array{status: string, latency_ms: int, message: string, remediation: ?string}
     */
    public function probeSmtp(
        string $host = 'mail.dpik.com.my',
        int $port = 465,
        ?string $username = null,
        ?string $password = null,
        int $timeout = 4
    ): array {
        $startTime = microtime(true);
        $cleanHost = preg_replace('#^ssl://|^tls://#', '', trim($host)) ?: 'mail.dpik.com.my';

        $transport = $port === 465 ? "ssl://{$cleanHost}" : "tcp://{$cleanHost}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "{$transport}:{$port}",
            $errno,
            $errstr,
            (float) $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if (! $socket) {
            return [
                'status' => 'error',
                'latency_ms' => $latencyMs,
                'message' => "Could not connect to SMTP server at {$cleanHost}:{$port} ({$errstr})",
                'remediation' => 'Verify your network allows outbound traffic on port 465 (SSL) or 587 (TLS).',
            ];
        }

        stream_set_timeout($socket, $timeout);
        $banner = fgets($socket, 1024);

        if ($banner === false || ! str_starts_with($banner, '220')) {
            fclose($socket);

            return [
                'status' => 'error',
                'latency_ms' => $latencyMs,
                'message' => 'SMTP server greeting failed or rejected connection.',
                'remediation' => 'Check if the SMTP service is operational on the server.',
            ];
        }

        fwrite($socket, "EHLO dpik-tadbir.local\r\n");
        $ehloResponse = '';
        while ($line = fgets($socket, 1024)) {
            $ehloResponse .= $line;
            if (preg_match('/^250[ -]/', $line) && ! preg_match('/^250-/', $line)) {
                break;
            }
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return [
            'status' => 'success',
            'latency_ms' => $latencyMs,
            'message' => "SMTP Server Ready on {$cleanHost}:{$port} (Ready to dispatch)",
            'remediation' => null,
        ];
    }
}
