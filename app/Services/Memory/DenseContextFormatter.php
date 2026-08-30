<?php

namespace App\Services\Memory;

use App\DTOs\MemorySearchResult;
use Illuminate\Support\Collection;

class DenseContextFormatter
{
    /**
     * Emits token-dense format: "YYYY-MM-DD | project:CODE | dm:TYPE | snippet"
     *
     * @param  Collection<int, MemorySearchResult>  $records
     */
    public function format(Collection $records): string
    {
        if ($records->isEmpty()) {
            return '';
        }

        $lines = [];

        foreach ($records as $record) {
            $date = $record->recordedAt ? substr($record->recordedAt, 0, 10) : 'UNDATED';
            $project = 'project:'.$record->projectCode;
            $marker = $record->decisionMarker ? 'dm:'.$record->decisionMarker : 'dm:context';
            $snippet = preg_replace('/\s+/', ' ', trim($record->summary)) ?? '';

            $lines[] = sprintf('%s | %s | %s | %s', $date, $project, $marker, $snippet);
        }

        return implode("\n", $lines);
    }
}
