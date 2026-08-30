<?php

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
