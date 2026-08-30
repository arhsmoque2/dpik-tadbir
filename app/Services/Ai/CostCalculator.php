<?php

namespace App\Services\Ai;

class CostCalculator
{
    /**
     * Pricing per 1,000,000 tokens in USD [input, output]
     *
     * @var array<string, array{input: float, output: float}>
     */
    protected array $pricing = [
        'claude-3-7-sonnet-20250219' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-haiku-20241022' => ['input' => 0.80, 'output' => 4.00],
        'gemini-2.5-flash' => ['input' => 0.15, 'output' => 0.60],
        'gemini-2.0-pro' => ['input' => 1.25, 'output' => 5.00],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
    ];

    protected float $myrExchangeRate = 4.45;

    /**
     * Calculate cost in USD and MYR based on token usage.
     *
     * @return array{usd: float, myr: float}
     */
    public function calculate(string $model, int $promptTokens, int $completionTokens): array
    {
        $rates = $this->pricing[$model] ?? ['input' => 1.00, 'output' => 3.00];

        $inputCost = ($promptTokens / 1_000_000) * $rates['input'];
        $outputCost = ($completionTokens / 1_000_000) * $rates['output'];
        $totalUsd = round($inputCost + $outputCost, 6);
        $totalMyr = round($totalUsd * $this->myrExchangeRate, 6);

        return [
            'usd' => $totalUsd,
            'myr' => $totalMyr,
        ];
    }
}
