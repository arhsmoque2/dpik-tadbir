<?php

use App\Services\Ai\CostCalculator;

test('cost calculator computes USD and MYR costs accurately', function () {
    $calculator = new CostCalculator;

    // 1,000,000 input tokens and 1,000,000 output tokens on Claude 3.7 Sonnet ($3.00 input, $15.00 output)
    $cost = $calculator->calculate('claude-3-7-sonnet-20250219', 1_000_000, 1_000_000);
    expect($cost['usd'])->toBe(18.0);
    expect($cost['myr'])->toBe(round(18.0 * 4.45, 6));

    // Gemini 2.5 Flash ($0.15 input, $0.60 output)
    $geminiCost = $calculator->calculate('gemini-2.5-flash', 100_000, 50_000);
    expect($geminiCost['usd'])->toBe(0.045);
    expect($geminiCost['myr'])->toBe(round(0.045 * 4.45, 6));
});
