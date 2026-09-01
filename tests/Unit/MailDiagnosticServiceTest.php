<?php

declare(strict_types=1);

namespace Tests\Unit;

class MockSocketStream
{
    /** @var resource|null */
    public $context;

    private int $position = 0;

    public string $readBuffer = '';

    public string $writeBuffer = '';

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->position = 0;
        $this->readBuffer = SocketFakeRegistry::$incomingPayload;

        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->position >= strlen($this->readBuffer)) {
            return '';
        }

        $remaining = substr($this->readBuffer, $this->position);
        $newlinePos = strpos($remaining, "\n");
        if ($newlinePos !== false) {
            $length = min($count, $newlinePos + 1);
        } else {
            $length = min($count, strlen($remaining));
        }

        $chunk = substr($this->readBuffer, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_write(string $data): int
    {
        $this->writeBuffer .= $data;

        return strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->readBuffer);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return ['size' => strlen($this->readBuffer)];
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }

    public function stream_close(): void
    {
        // closed
    }
}

class SocketFakeRegistry
{
    /**
     * @var (callable(string, int|null, string|null, float|null, int, mixed): mixed)|null
     */
    public static $streamSocketHandler = null;

    public static string $incomingPayload = '';

    public static function reset(): void
    {
        self::$streamSocketHandler = null;
        self::$incomingPayload = '';
    }

    public static function createMockStream(string $incoming): mixed
    {
        self::$incomingPayload = $incoming;
        if (! in_array('fake-socket', stream_get_wrappers(), true)) {
            stream_wrapper_register('fake-socket', MockSocketStream::class);
        }

        return fopen('fake-socket://connection', 'r+');
    }
}

namespace App\Services\Mcp;

use Tests\Unit\SocketFakeRegistry;

if (! function_exists('App\Services\Mcp\stream_socket_client')) {
    /**
     * Namespace-level interceptor for stream_socket_client to allow hermetic test execution.
     *
     * @param  int|null  $errno
     * @param  string|null  $errstr
     * @return resource|false
     */
    function stream_socket_client(
        string $address,
        &$errno = null,
        &$errstr = null,
        ?float $timeout = null,
        int $flags = STREAM_CLIENT_CONNECT,
        $context = null
    ) {
        if (SocketFakeRegistry::$streamSocketHandler !== null) {
            return (SocketFakeRegistry::$streamSocketHandler)($address, $errno, $errstr, $timeout, $flags, $context);
        }

        return \stream_socket_client($address, $errno, $errstr, (float) $timeout, $flags, $context);
    }
}

namespace Tests\Unit;

use App\Services\Mcp\MailDiagnosticService;

beforeEach(function () {
    SocketFakeRegistry::reset();
});

afterEach(function () {
    SocketFakeRegistry::reset();
});

it('handles imap socket failure gracefully on invalid host or closed port', function () {
    SocketFakeRegistry::$streamSocketHandler = function (string $address, &$errno, &$errstr) {
        $errno = 111;
        $errstr = 'Connection refused';

        return false;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeImap('127.0.0.1', 19999, 'user', 'pass', 1);

    expect($result['status'])->toBe('error')
        ->and($result['latency_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['message'])->toContain('Could not connect to IMAP server')
        ->and($result['remediation'])->not->toBeNull();
});

it('handles smtp socket failure gracefully on invalid host or closed port', function () {
    SocketFakeRegistry::$streamSocketHandler = function (string $address, &$errno, &$errstr) {
        $errno = 111;
        $errstr = 'Connection refused';

        return false;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeSmtp('127.0.0.1', 19998, 'user', 'pass', 1);

    expect($result['status'])->toBe('error')
        ->and($result['latency_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['message'])->toContain('Could not connect to SMTP server')
        ->and($result['remediation'])->not->toBeNull();
});

it('handles imap greeting banner rejection or unexpected response', function () {
    $mockStream = SocketFakeRegistry::createMockStream("* BYE Service not available\r\n");

    SocketFakeRegistry::$streamSocketHandler = function () use ($mockStream) {
        return $mockStream;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeImap('ssl://mail.dpik.com.my', 993, null, null, 2);

    expect($result['status'])->toBe('error')
        ->and($result['message'])->toBe('IMAP greeting failed or returned unexpected response.')
        ->and($result['remediation'])->toContain('Check if the IMAP service is running');
});

it('handles imap probe without credentials returning ssl greeting', function () {
    $mockStream = SocketFakeRegistry::createMockStream("* OK [CAPABILITY IMAP4rev1] Dovecot ready.\r\n");

    SocketFakeRegistry::$streamSocketHandler = function () use ($mockStream) {
        return $mockStream;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeImap('tls://mail.dpik.com.my', 143, null, null, 2);

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toContain('IMAP Server Reachable on mail.dpik.com.my:143')
        ->and($result['remediation'])->toBeNull();
});

it('handles imap authentication success with credentials', function () {
    $mockStream = SocketFakeRegistry::createMockStream("* OK Dovecot ready.\r\na001 OK Logged in\r\n");

    SocketFakeRegistry::$streamSocketHandler = function () use ($mockStream) {
        return $mockStream;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeImap('mail.dpik.com.my', 993, 'rahman@dpik.com.my', 'valid_pass', 2);

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toContain('IMAP Authenticated successfully as rahman@dpik.com.my')
        ->and($result['remediation'])->toBeNull();
});

it('handles imap authentication failure with bad credentials', function () {
    $mockStream = SocketFakeRegistry::createMockStream("* OK Dovecot ready.\r\na001 NO [AUTHENTICATIONFAILED] Invalid credentials\r\n");

    SocketFakeRegistry::$streamSocketHandler = function () use ($mockStream) {
        return $mockStream;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeImap('mail.dpik.com.my', 993, 'rahman@dpik.com.my', 'wrong_pass', 2);

    expect($result['status'])->toBe('error')
        ->and($result['message'])->toContain('IMAP Connected but authentication failed')
        ->and($result['remediation'])->toContain('Verify your company email password');
});

it('handles smtp greeting banner failure or rejection', function () {
    $mockStream = SocketFakeRegistry::createMockStream("554 Service unavailable\r\n");

    SocketFakeRegistry::$streamSocketHandler = function () use ($mockStream) {
        return $mockStream;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeSmtp('mail.dpik.com.my', 465, null, null, 2);

    expect($result['status'])->toBe('error')
        ->and($result['message'])->toBe('SMTP server greeting failed or rejected connection.')
        ->and($result['remediation'])->toContain('Check if the SMTP service is operational');
});

it('handles smtp probe without credentials returning ready on standard port', function () {
    $mockStream = SocketFakeRegistry::createMockStream("220 mail.dpik.com.my ESMTP Exim\r\n250-mail.dpik.com.my Hello\r\n250 AUTH LOGIN PLAIN\r\n");

    SocketFakeRegistry::$streamSocketHandler = function () use ($mockStream) {
        return $mockStream;
    };

    $service = new MailDiagnosticService;
    $result = $service->probeSmtp('ssl://mail.dpik.com.my', 587, null, null, 2);

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toContain('SMTP Server Ready on mail.dpik.com.my:587')
        ->and($result['remediation'])->toBeNull();
});
