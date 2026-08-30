<?php

use App\Models\User;
use App\Services\Ai\LlmGatewayService;

test('llm gateway returns active models and processes simulated turns', function () {
    $gateway = new LlmGatewayService;

    expect($gateway->getActiveProvider())->not->toBeEmpty();
    expect($gateway->getActiveModel())->not->toBeEmpty();

    // Normal message
    $res1 = $gateway->complete([
        ['role' => 'user', 'content' => 'Hello there'],
    ]);
    expect($res1['content'])->toContain('DPIK Tadbir Copilot');

    // Inbox / Delta trigger
    $res2 = $gateway->complete([
        ['role' => 'user', 'content' => 'Check my inbox delta please'],
    ]);
    expect($res2['tool_calls'])->toBeArray();
    expect($res2['tool_calls'][0]['name'])->toBe('outlook_list_inbox_delta');

    // Draft trigger
    $res3 = $gateway->complete([
        ['role' => 'user', 'content' => 'Please prepare a draft reply'],
    ]);
    expect($res3['tool_calls'])->toBeArray();
    expect($res3['tool_calls'][0]['name'])->toBe('propose_action_card');
});

test('llm gateway invokes openrouter endpoint with custom referer and title headers', function () {
    $gateway = new LlmGatewayService;

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => function ($request) {
            expect($request->hasHeader('HTTP-Referer'))->toBeTrue();
            expect($request->header('HTTP-Referer')[0])->toBe('https://tadbir.dpik.com.my');
            expect($request->hasHeader('X-Title'))->toBeTrue();
            expect($request->header('X-Title')[0])->toBe('Tadbir AI Copilot');
            expect($request->header('Authorization')[0])->toBe('Bearer sk-or-v1-my-mock-key'); // gitleaks:allow

            return Http::response([
                'id' => 'gen-12345',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Response from DeepSeek R1 reasoning model.',
                        ],
                    ],
                ],
            ], 200);
        },
    ]);

    $res = $gateway->complete(
        messages: [['role' => 'user', 'content' => 'Solve this math equation']],
        options: [
            'provider' => 'openrouter',
            'model' => 'deepseek/deepseek-r1',
            'live' => true,
            'user' => new User(['openrouter_api_key' => 'sk-or-v1-my-mock-key']), // gitleaks:allow
        ]
    );

    expect($res['provider'])->toBe('openrouter');
    expect($res['model'])->toBe('deepseek/deepseek-r1');
    expect($res['content'])->toBe('Response from DeepSeek R1 reasoning model.');
});

test('llm gateway passes through exact upstream openrouter error messages', function () {
    $gateway = new LlmGatewayService;

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'error' => [
                'code' => 402,
                'message' => 'Insufficient credits: your balance is 0.00 credits.',
            ],
        ], 402),
    ]);

    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeOpenRouter');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(
        $gateway,
        'deepseek/deepseek-r1',
        [['role' => 'user', 'content' => 'Hi']],
        [],
        'sk-or-v1-no-balance-key', // gitleaks:allow
        []
    ))->toThrow(RuntimeException::class, 'Insufficient credits');
});

test('llm gateway setActiveModel and fakeSequence edge cases work', function () {
    $gateway = new LlmGatewayService;
    $gateway->setActiveModel('openrouter', 'deepseek/deepseek-r1');
    expect($gateway->getActiveModel())->toBe('deepseek/deepseek-r1');

    LlmGatewayService::fakeSequence([
        new RuntimeException('Simulated sequence failure'),
    ]);

    expect(fn () => $gateway->complete([['role' => 'user', 'content' => 'Test']]))
        ->toThrow(RuntimeException::class, 'Simulated sequence failure');

    LlmGatewayService::fakeSequence([
        ['content' => 'Faked without provider'],
    ]);

    $res = $gateway->complete([['role' => 'user', 'content' => 'Test']], options: ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-r1']);
    expect($res['provider'])->toBe('openrouter');
    expect($res['content'])->toBe('Faked without provider');

    LlmGatewayService::resetFakes();
});

test('invokeOpenRouter throws exception when api key is empty', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeOpenRouter');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($gateway, 'deepseek/deepseek-r1', [], [], '', []))
        ->toThrow(RuntimeException::class, 'OpenRouter API key is missing');
});

test('invokeOpenRouter handles raw string gateway error responses', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeOpenRouter');
    $method->setAccessible(true);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response('Raw gateway string failure', 502),
    ]);

    expect(fn () => $method->invoke($gateway, 'deepseek/deepseek-r1', [['role' => 'user', 'content' => 'Hi']], [], 'sk-or-v1-my-mock-key', [])) // gitleaks:allow
        ->toThrow(RuntimeException::class, 'OpenRouter API error (HTTP 502)');
});

test('invokeOpenRouter parses function tool calls from openrouter payload', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeOpenRouter');
    $method->setAccessible(true);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'function' => [
                                    'name' => 'search_projects',
                                    'arguments' => json_encode(['keyword' => 'bridge']),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $res = $method->invoke($gateway, 'deepseek/deepseek-r1', [['role' => 'user', 'content' => 'Search']], [['type' => 'function']], 'sk-or-v1-my-mock-key', []); // gitleaks:allow
    expect($res['tool_calls'])->toHaveCount(1);
    expect($res['tool_calls'][0]['name'])->toBe('search_projects');
});

test('probeOpenRouterKey handles connection exceptions', function () {
    $gateway = new LlmGatewayService;

    Http::fake([
        'https://openrouter.ai/api/v1/auth/key' => function () {
            throw new Exception('Connection timeout');
        },
    ]);

    $res = $gateway->probeOpenRouterKey('sk-or-v1-valid-pattern-key'); // gitleaks:allow
    expect($res['status'])->toBe('network_error');
    expect($res['error_code'])->toBe('CONNECTION_FAILED');
    expect($res['error_message'])->toContain('Connection timeout');
});
