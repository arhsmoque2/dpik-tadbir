<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\ChatSession;
use App\Services\Ai\PiiDetector;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class SessionExportService
{
    private const DECISION_MARKER_PATTERNS = [
        '/decid(ed|ing|e)\s+to/i',
        '/we\s+will\s+(use|choose|proceed|adopt)/i',
        '/architectural\s+decision/i',
        '/adr[-\s:]?\d+/i',
        '/action\s+items?[:\s]/i',
        '/approved\s+to\s+send/i',
        '/rejection\s+reason/i',
        '/trade-?off/i',
    ];

    public function __construct(
        protected ?PiiDetector $piiDetector = null
    ) {
        $this->piiDetector = $piiDetector ?? app(PiiDetector::class);
    }

    /**
     * Get default filename according to contract:
     * e.g. dpik-tadbir-sessions-2026-09-01.db or dpik-tadbir-session-12-2026-09-01.jsonl
     */
    public function getDefaultFilename(string $extension, ?int $sessionId = null): string
    {
        $appName = Str::slug((string) config('app.name', 'dpik-tadbir'));
        $date = now()->format('Y-m-d');
        $ext = ltrim($extension, '.');

        if ($sessionId !== null) {
            return "{$appName}-session-{$sessionId}-{$date}.{$ext}";
        }

        return "{$appName}-sessions-{$date}.{$ext}";
    }

    /**
     * Get target default export directory.
     */
    public function getExportDirectory(): string
    {
        $dir = storage_path('app/exports');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        return $dir;
    }

    /**
     * Export sessions to SQLite database matching the arh-session-reader FTS5 schema.
     */
    public function exportToDb(?int $sessionId = null, ?string $outputPath = null): string
    {
        $targetPath = $outputPath ?? ($this->getExportDirectory().DIRECTORY_SEPARATOR.$this->getDefaultFilename('db', $sessionId));
        $targetDir = dirname($targetPath);

        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true, true);
        }

        if (File::exists($targetPath)) {
            File::delete($targetPath);
        }

        $pdo = new PDO('sqlite:'.$targetPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL;');

        // Create arh-session-reader compatible schema
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS sessions (
              slug            TEXT PRIMARY KEY,
              provider        TEXT NOT NULL,
              agent_label     TEXT NOT NULL,
              session_id      TEXT NOT NULL,
              workspace       TEXT,
              git_branch      TEXT,
              intent          TEXT,
              entry_points    TEXT,
              decision_marker INTEGER NOT NULL DEFAULT 0,
              started_at      TEXT,
              updated_at      TEXT,
              source_path     TEXT NOT NULL,
              source_mtime    REAL NOT NULL,
              source_size     INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS turns (
              id            INTEGER PRIMARY KEY AUTOINCREMENT,
              session_slug  TEXT NOT NULL REFERENCES sessions(slug) ON DELETE CASCADE,
              turn_no       INTEGER NOT NULL,
              role          TEXT NOT NULL,
              text          TEXT NOT NULL,
              ts            TEXT
            );
            CREATE VIRTUAL TABLE IF NOT EXISTS turns_fts USING fts5(
              text, content="turns", content_rowid="id",
              tokenize="porter unicode61 remove_diacritics 1", prefix="2 3 4"
            );
            CREATE TRIGGER IF NOT EXISTS turns_ai AFTER INSERT ON turns BEGIN
              INSERT INTO turns_fts(rowid, text) VALUES (new.id, new.text);
            END;
            CREATE TRIGGER IF NOT EXISTS turns_ad AFTER DELETE ON turns BEGIN
              INSERT INTO turns_fts(turns_fts, rowid, text) VALUES("delete", old.id, old.text);
            END;
            CREATE TRIGGER IF NOT EXISTS turns_au AFTER UPDATE ON turns BEGIN
              INSERT INTO turns_fts(turns_fts, rowid, text) VALUES("delete", old.id, old.text);
              INSERT INTO turns_fts(rowid, text) VALUES (new.id, new.text);
            END;
            CREATE INDEX IF NOT EXISTS idx_turns_slug ON turns(session_slug);
            CREATE TABLE IF NOT EXISTS health (
              provider        TEXT PRIMARY KEY,
              seen_count      INTEGER NOT NULL,
              peak_seen_count INTEGER NOT NULL,
              root_existed    INTEGER NOT NULL,
              checked_at      TEXT NOT NULL
            );
        ');

        $query = ChatSession::with(['user', 'messages'])->orderBy('id', 'asc');
        if ($sessionId !== null) {
            $query->where('id', $sessionId);
        }

        $sessions = $query->get();
        $appName = Str::slug((string) config('app.name', 'dpik-tadbir'));

        $insertSession = $pdo->prepare('
            INSERT INTO sessions (
                slug, provider, agent_label, session_id, workspace, git_branch,
                intent, entry_points, decision_marker, started_at, updated_at,
                source_path, source_mtime, source_size
            ) VALUES (
                :slug, :provider, :agent_label, :session_id, :workspace, :git_branch,
                :intent, :entry_points, :decision_marker, :started_at, :updated_at,
                :source_path, :source_mtime, :source_size
            )
        ');

        $insertTurn = $pdo->prepare('
            INSERT INTO turns (session_slug, turn_no, role, text, ts)
            VALUES (:session_slug, :turn_no, :role, :text, :ts)
        ');

        $pdo->beginTransaction();

        foreach ($sessions as $session) {
            $slug = "{$appName}-session-{$session->id}";
            $firstUserMessage = $session->messages->firstWhere('role', 'user');
            $intent = $firstUserMessage ? Str::limit((string) $firstUserMessage->content, 180) : ($session->title ?: 'Untitled Session');

            $allText = $session->messages->pluck('content')->implode(' ');
            $hasDecisionMarker = $this->hasDecisionMarker($allText) ? 1 : 0;

            $entryPoints = [];
            foreach ($session->messages as $msg) {
                $toolCalls = $msg->tool_calls;
                if (is_array($toolCalls)) {
                    /** @var list<mixed>|array<string, mixed> $toolCalls */
                    foreach ($toolCalls as $tc) {
                        if (is_array($tc) && isset($tc['name'])) {
                            $name = (string) $tc['name'];
                            if (! in_array($name, $entryPoints, true)) {
                                $entryPoints[] = $name;
                            }
                        }
                    }
                }
            }

            $insertSession->execute([
                ':slug' => $slug,
                ':provider' => 'tadbir',
                ':agent_label' => 'executive-copilot',
                ':session_id' => (string) $session->id,
                ':workspace' => $appName,
                ':git_branch' => 'main',
                ':intent' => $intent,
                ':entry_points' => json_encode($entryPoints),
                ':decision_marker' => $hasDecisionMarker,
                ':started_at' => $session->created_at?->toISOString() ?? now()->toISOString(),
                ':updated_at' => $session->updated_at?->toISOString() ?? now()->toISOString(),
                ':source_path' => $targetPath,
                ':source_mtime' => (float) time(),
                ':source_size' => (int) $session->messages->count(),
            ]);

            $turnNo = 1;
            foreach ($session->messages as $message) {
                $content = (string) $message->content;
                if (! empty($message->tool_calls)) {
                    $content .= "\n[Tool Calls: ".json_encode($message->tool_calls).']';
                }
                if (! empty($message->tool_results)) {
                    $content .= "\n[Tool Results: ".json_encode($message->tool_results).']';
                }

                $insertTurn->execute([
                    ':session_slug' => $slug,
                    ':turn_no' => $turnNo++,
                    ':role' => $message->role,
                    ':text' => $content,
                    ':ts' => $message->created_at?->toISOString() ?? now()->toISOString(),
                ]);
            }
        }

        $insertHealth = $pdo->prepare('
            INSERT OR REPLACE INTO health (provider, seen_count, peak_seen_count, root_existed, checked_at)
            VALUES (:provider, :seen_count, :peak_seen_count, :root_existed, :checked_at)
        ');
        $insertHealth->execute([
            ':provider' => 'tadbir',
            ':seen_count' => count($sessions),
            ':peak_seen_count' => count($sessions),
            ':root_existed' => 1,
            ':checked_at' => now()->toISOString(),
        ]);

        $pdo->commit();

        return $targetPath;
    }

    /**
     * Export sessions to JSON Lines (.jsonl) format.
     */
    public function exportToJsonl(?int $sessionId = null, ?string $outputPath = null): string
    {
        $targetPath = $outputPath ?? ($this->getExportDirectory().DIRECTORY_SEPARATOR.$this->getDefaultFilename('jsonl', $sessionId));
        $targetDir = dirname($targetPath);

        if (! File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true, true);
        }

        $query = ChatSession::with(['user', 'messages'])->orderBy('id', 'asc');
        if ($sessionId !== null) {
            $query->where('id', $sessionId);
        }

        $sessions = $query->get();
        $appName = Str::slug((string) config('app.name', 'dpik-tadbir'));

        $handle = fopen($targetPath, 'wb');
        if (! $handle) {
            throw new RuntimeException("Unable to open file for writing at {$targetPath}");
        }

        foreach ($sessions as $session) {
            $turns = [];
            $turnNo = 1;

            foreach ($session->messages as $msg) {
                $turns[] = [
                    'turn_no' => $turnNo++,
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'tool_calls' => $msg->tool_calls,
                    'tool_results' => $msg->tool_results,
                    'metadata' => $msg->metadata,
                    'timestamp' => $msg->created_at?->toISOString() ?? now()->toISOString(),
                ];
            }

            $firstUserMsg = $session->messages->firstWhere('role', 'user');
            $allText = $session->messages->pluck('content')->implode(' ');

            $record = [
                'session_id' => $session->id,
                'slug' => "{$appName}-session-{$session->id}",
                'app_name' => $appName,
                'provider' => 'tadbir',
                'agent_label' => 'executive-copilot',
                'title' => $session->title,
                'context_mode' => $session->context_mode,
                'bundle_id' => $session->bundle_id,
                'intent' => $firstUserMsg ? Str::limit((string) $firstUserMsg->content, 180) : ($session->title ?: 'Untitled Session'),
                'decision_marker' => $this->hasDecisionMarker($allText),
                'user' => $session->user ? [
                    'id' => $session->user->id,
                    'name' => $session->user->name,
                    'email' => $session->user->email,
                ] : null,
                'started_at' => $session->created_at?->toISOString() ?? now()->toISOString(),
                'updated_at' => $session->updated_at?->toISOString() ?? now()->toISOString(),
                'turns_count' => count($turns),
                'turns' => $turns,
            ];

            fwrite($handle, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }

        fclose($handle);

        return $targetPath;
    }

    /**
     * Export all formats simultaneously.
     *
     * @return array{db: string, jsonl: string, sessions_count: int}
     */
    public function exportAll(?int $sessionId = null, ?string $outputDir = null): array
    {
        $dir = $outputDir ?? $this->getExportDirectory();
        $dbPath = $dir.DIRECTORY_SEPARATOR.$this->getDefaultFilename('db', $sessionId);
        $jsonlPath = $dir.DIRECTORY_SEPARATOR.$this->getDefaultFilename('jsonl', $sessionId);

        $dbResult = $this->exportToDb($sessionId, $dbPath);
        $jsonlResult = $this->exportToJsonl($sessionId, $jsonlPath);

        $count = ChatSession::query()
            ->when($sessionId !== null, fn ($q) => $q->where('id', $sessionId))
            ->count();

        return [
            'db' => $dbResult,
            'jsonl' => $jsonlResult,
            'sessions_count' => $count,
        ];
    }

    protected function hasDecisionMarker(string $text): bool
    {
        foreach (self::DECISION_MARKER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}
