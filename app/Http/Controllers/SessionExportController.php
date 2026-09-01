<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Export\SessionExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SessionExportController extends Controller
{
    /**
     * Download exported chat sessions as SQLite DB or JSONL.
     */
    public function download(Request $request, SessionExportService $exporter, string $format = 'db'): BinaryFileResponse
    {
        $format = strtolower($format) === 'jsonl' ? 'jsonl' : 'db';
        $sessionId = $request->query('session') ? (int) $request->query('session') : null;

        if ($format === 'jsonl') {
            $path = $exporter->exportToJsonl($sessionId);
            $filename = $exporter->getDefaultFilename('jsonl', $sessionId);
            $headers = ['Content-Type' => 'application/x-ndjson'];
        } else {
            $path = $exporter->exportToDb($sessionId);
            $filename = $exporter->getDefaultFilename('db', $sessionId);
            $headers = ['Content-Type' => 'application/x-sqlite3'];
        }

        return response()->download($path, $filename, $headers);
    }
}
