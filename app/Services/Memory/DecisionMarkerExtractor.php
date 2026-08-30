<?php

namespace App\Services\Memory;

class DecisionMarkerExtractor
{
    /**
     * Extracts decision markers and commitments from raw text.
     *
     * @return array{decisions: list<string>, commitments: list<string>}
     */
    public function extract(string $text): array
    {
        $decisions = [];
        $commitments = [];

        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/(?:decision|keputusan|decided|disahkan)\s*[:\-]\s*(.+)/i', $trimmed, $m)) {
                $decisions[] = trim($m[1]);
            } elseif (preg_match('/(?:commitment|komitmen|promise|akan\s+hantar|deadline|by\s+\d{1,2}\/\d{1,2})\s*[:\-]\s*(.+)/i', $trimmed, $m)) {
                $commitments[] = trim($m[1]);
            }
        }

        return [
            'decisions' => array_values(array_unique($decisions)),
            'commitments' => array_values(array_unique($commitments)),
        ];
    }
}
