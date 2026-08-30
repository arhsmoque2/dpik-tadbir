<?php

namespace App\Services\Ai;

class PiiDetector
{
    /**
     * @var array<string, string>
     */
    protected array $patterns = [
        'nric_formatted' => '/\b\d{6}-\d{2}-\d{4}\b/',
        'nric_unformatted' => '/\b\d{12}\b/',
        'credit_card' => '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13})\b/',
        'secret_key' => '/\b(sk-[a-zA-Z0-9_\-]{20,}|ghp_[a-zA-Z0-9]{20,}|bearer\s+[a-zA-Z0-9_\-\.]{25,})\b/i',
        'malaysian_mobile' => '/\b(?:\+?6?01)[0-46-9]-*[0-9]{7,8}\b/',
    ];

    /**
     * Check if text contains any PII pattern.
     */
    public function hasPii(string $text): bool
    {
        return ! empty($this->detect($text));
    }

    /**
     * Detect all PII matches with their matched type.
     *
     * @return array<string, list<string>>
     */
    public function detect(string $text): array
    {
        $findings = [];

        foreach ($this->patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                /** @var list<string> $matchedStrings */
                $matchedStrings = array_values(array_unique($matches[0]));
                if (! empty($matchedStrings)) {
                    $findings[$type] = $matchedStrings;
                }
            }
        }

        return $findings;
    }

    /**
     * Redact all identified PII in text.
     */
    public function redact(string $text): string
    {
        $redacted = $text;

        foreach ($this->patterns as $type => $pattern) {
            $replacement = match ($type) {
                'nric_formatted', 'nric_unformatted' => '[REDACTED_NRIC]',
                'credit_card' => '[REDACTED_CREDIT_CARD]',
                'secret_key' => '[REDACTED_SECRET]',
                'malaysian_mobile' => '[REDACTED_PHONE]',
                default => '[REDACTED_PII]',
            };

            $result = preg_replace($pattern, $replacement, $redacted);
            if ($result !== null) {
                $redacted = $result;
            }
        }

        return $redacted;
    }

    /**
     * Return count of detected PII occurrences by type without exposing sensitive plaintext values.
     *
     * @return array<string, int>
     */
    public function detectCounts(string $text): array
    {
        $counts = [];
        foreach ($this->patterns as $type => $pattern) {
            $matched = preg_match_all($pattern, $text);
            if ($matched !== false && $matched > 0) {
                $counts[$type] = $matched;
            }
        }

        return $counts;
    }

    /**
     * Recursively redact PII from nested array structures or JSON payloads.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redactArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->redactArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
