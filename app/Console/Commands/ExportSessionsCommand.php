<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Export\SessionExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'session:export
                            {--format=all : Export format: db, jsonl, or all}
                            {--session= : Export a specific session ID}
                            {--output= : Custom destination file path or directory}
                            {--stdout : Stream JSONL directly to stdout}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export chat sessions and tool trajectories into SQLite (.db) FTS5 or JSONL for ARH Session Reader or backup';

    /**
     * Execute the console command.
     */
    public function handle(SessionExportService $exporter): int
    {
        $format = strtolower((string) ($this->option('format') ?: 'all'));
        $sessionId = $this->option('session') !== null ? (int) $this->option('session') : null;
        $output = $this->option('output') ? (string) $this->option('output') : null;
        $stdout = (bool) $this->option('stdout');

        if ($stdout) {
            $tempJsonl = tempnam(sys_get_temp_dir(), 'tadbir_export_');
            $exporter->exportToJsonl($sessionId, $tempJsonl);
            $this->output->write((string) file_get_contents($tempJsonl));
            @unlink($tempJsonl);

            return self::SUCCESS;
        }

        $this->info('Starting DPIK Tadbir Session Export...');

        $files = [];

        if ($format === 'db') {
            $path = $exporter->exportToDb($sessionId, $output);
            $files['SQLite FTS5 (.db)'] = $path;
        } elseif ($format === 'jsonl') {
            $path = $exporter->exportToJsonl($sessionId, $output);
            $files['JSON Lines (.jsonl)'] = $path;
        } else {
            $result = $exporter->exportAll($sessionId, $output);
            $files['SQLite FTS5 (.db)'] = $result['db'];
            $files['JSON Lines (.jsonl)'] = $result['jsonl'];
        }

        $tableRows = [];
        foreach ($files as $type => $filePath) {
            $size = File::exists($filePath) ? round(File::size($filePath) / 1024, 2).' KB' : '0 KB';
            $tableRows[] = [$type, $filePath, $size];
        }

        $this->table(['Format', 'File Path', 'Size'], $tableRows);
        $this->newLine();
        $this->info('Export completed successfully. Ready for sharing via Taildrop, Google Drive, or ARH Session Reader indexing.');

        return self::SUCCESS;
    }
}
