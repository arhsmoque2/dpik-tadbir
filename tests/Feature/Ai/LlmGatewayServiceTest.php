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

test('llm gateway invokes anthropic messages api with system prompt and correct headers', function () {
    $gateway = new LlmGatewayService;

    Http::fake([
        'https://api.anthropic.com/v1/messages' => function ($request) {
            expect($request->header('x-api-key')[0])->toBe('sk-ant-my-mock-key'); // gitleaks:allow
            expect($request->header('anthropic-version')[0])->toBe('2023-06-01');
            expect($request->data()['system'])->toBe('You are the DPIK Tadbir executive copilot.');

            return Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Sungai Udang bridge repair is on schedule.'],
                ],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 120, 'output_tokens' => 18],
            ], 200);
        },
    ]);

    $res = $gateway->complete(
        messages: [['role' => 'user', 'content' => 'Status update please']],
        options: [
            'provider' => 'anthropic',
            'model' => 'claude-3-7-sonnet-20250219',
            'live' => true,
            'system' => 'You are the DPIK Tadbir executive copilot.',
            'user' => new User(['anthropic_api_key' => 'sk-ant-my-mock-key']), // gitleaks:allow
        ]
    );

    expect($res['provider'])->toBe('anthropic');
    expect($res['content'])->toBe('Sungai Udang bridge repair is on schedule.');
    expect($res['stop_reason'])->toBe('end_turn');
    expect($res['tool_calls'])->toBe([]);
});

test('llm gateway parses anthropic tool_use blocks into normalized tool_calls', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeAnthropic');
    $method->setAccessible(true);

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'Let me check that.'],
                ['type' => 'tool_use', 'id' => 'toolu_01abc', 'name' => 'query_project_register', 'input' => ['query' => 'FT264']],
            ],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 50, 'output_tokens' => 12],
        ], 200),
    ]);

    $tools = [
        ['name' => 'query_project_register', 'description' => 'Searches the register.', 'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]],
    ];

    $res = $method->invoke($gateway, 'claude-3-7-sonnet-20250219', [['role' => 'user', 'content' => 'Check FT264']], $tools, 'sk-ant-mock', []); // gitleaks:allow

    expect($res['stop_reason'])->toBe('tool_use');
    expect($res['tool_calls'])->toHaveCount(1);
    expect($res['tool_calls'][0]['id'])->toBe('toolu_01abc');
    expect($res['tool_calls'][0]['name'])->toBe('query_project_register');
    expect($res['tool_calls'][0]['arguments'])->toBe(['query' => 'FT264']);
    expect($res['input_tokens'])->toBe(50);
    expect($res['output_tokens'])->toBe(12);
});

test('invokeAnthropic throws exception when api key is empty', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeAnthropic');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($gateway, 'claude-3-7-sonnet-20250219', [], [], '', []))
        ->toThrow(RuntimeException::class, 'Anthropic API key is missing');
});

test('invokeAnthropic passes through exact upstream error messages', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeAnthropic');
    $method->setAccessible(true);

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'error' => ['type' => 'invalid_x_api_key', 'message' => 'invalid x-api-key'],
        ], 401),
    ]);

    expect(fn () => $method->invoke($gateway, 'claude-3-7-sonnet-20250219', [['role' => 'user', 'content' => 'Hi']], [], 'sk-ant-bad-key', [])) // gitleaks:allow
        ->toThrow(RuntimeException::class, 'invalid x-api-key');
});

test('toAnthropicMessages merges multiple tool results from one turn into a single user message', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('toAnthropicMessages');
    $method->setAccessible(true);

    $neutral = [
        ['role' => 'user', 'content' => 'Check both projects'],
        [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [
                ['id' => 'call_1', 'name' => 'query_project_register', 'arguments' => ['query' => 'A']],
                ['id' => 'call_2', 'name' => 'query_project_register', 'arguments' => ['query' => 'B']],
            ],
        ],
        ['role' => 'tool', 'content' => '{"count":1}', 'tool_call_id' => 'call_1', 'is_error' => false],
        ['role' => 'tool', 'content' => '{"error":"not found"}', 'tool_call_id' => 'call_2', 'is_error' => true],
    ];

    $result = $method->invoke($gateway, $neutral);

    expect($result)->toHaveCount(3); // user, assistant(2 tool_use blocks), user(2 merged tool_result blocks)
    expect($result[1]['content'])->toHaveCount(2);
    expect($result[1]['content'][0]['type'])->toBe('tool_use');
    expect($result[2]['role'])->toBe('user');
    expect($result[2]['content'])->toHaveCount(2);
    expect($result[2]['content'][0]['type'])->toBe('tool_result');
    expect($result[2]['content'][0]['tool_use_id'])->toBe('call_1');
    expect($result[2]['content'][1]['is_error'])->toBeTrue();
});

test('toOpenAiMessages translates assistant tool_calls and tool results to OpenAI wire format', function () {
    $gateway = new LlmGatewayService;
    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('toOpenAiMessages');
    $method->setAccessible(true);

    $neutral = [
        ['role' => 'user', 'content' => 'Check FT264'],
        [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [
                ['id' => 'call_1', 'name' => 'query_project_register', 'arguments' => ['query' => 'FT264']],
            ],
        ],
        ['role' => 'tool', 'content' => '{"count":0}', 'tool_call_id' => 'call_1', 'is_error' => false],
    ];

    $result = $method->invoke($gateway, $neutral, 'System prompt text');

    expect($result[0])->toBe(['role' => 'system', 'content' => 'System prompt text']);
    expect($result[2]['role'])->toBe('assistant');
    expect($result[2]['tool_calls'][0]['function']['name'])->toBe('query_project_register');
    expect($result[2]['tool_calls'][0]['function']['arguments'])->toBe(json_encode(['query' => 'FT264']));
    expect($result[3])->toBe(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"count":0}']);
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
