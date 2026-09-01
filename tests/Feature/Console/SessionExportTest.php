<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Export\SessionExportService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['auth.enabled' => true]);

    $this->user = User::create([
        'name' => 'Rahman DPIK',
        'email' => 'rahman@dpik.com.my',
        'role' => 'super_admin',
        'password' => bcrypt('password123'),
    ]);

    $this->session1 = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Tender Review Session',
        'context_mode' => 'inbox_triage',
    ]);

    ChatMessage::create([
        'chat_session_id' => $this->session1->id,
        'role' => 'user',
        'content' => 'Find all tender emails regarding JKR water supply',
    ]);

    ChatMessage::create([
        'chat_session_id' => $this->session1->id,
        'role' => 'assistant',
        'content' => 'Found 2 tender documents. We decided to adopt REV-B for final submission.',
        'tool_calls' => [['name' => 'search_mail', 'arguments' => ['query' => 'JKR']]],
        'tool_results' => [['status' => 'success', 'count' => 2]],
    ]);

    $this->session2 = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Personal Note Taking',
        'context_mode' => 'general',
    ]);

    ChatMessage::create([
        'chat_session_id' => $this->session2->id,
        'role' => 'user',
        'content' => 'Take a note on site inspection scheduled for Thursday.',
    ]);
});

it('generates consistent default export filenames with app name and date', function () {
    $exporter = app(SessionExportService::class);
    $date = now()->format('Y-m-d');

    expect($exporter->getDefaultFilename('db'))->toBe("dpik-tadbir-sessions-{$date}.db")
        ->and($exporter->getDefaultFilename('jsonl'))->toBe("dpik-tadbir-sessions-{$date}.jsonl")
        ->and($exporter->getDefaultFilename('db', 42))->toBe("dpik-tadbir-session-42-{$date}.db");
});

it('exports sessions to SQLite FTS5 database compatible with arh-session-reader', function () {
    $exporter = app(SessionExportService::class);
    $dbPath = $exporter->exportToDb();

    expect(File::exists($dbPath))->toBeTrue();

    $pdo = new PDO('sqlite:'.$dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify tables exist
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    expect($tables)->toContain('sessions')
        ->and($tables)->toContain('turns')
        ->and($tables)->toContain('turns_fts');

    // Verify session data
    $sessions = $pdo->query('SELECT * FROM sessions ORDER BY started_at ASC')->fetchAll(PDO::FETCH_ASSOC);
    expect(count($sessions))->toBe(2);

    $first = $sessions[0];
    expect($first['provider'])->toBe('tadbir')
        ->and($first['agent_label'])->toBe('executive-copilot')
        ->and($first['decision_marker'])->toBe(1) // "decided to" keyword
        ->and($first['intent'])->toContain('Find all tender emails');

    // Verify FTS5 full-text query
    $ftsResults = $pdo->query("SELECT * FROM turns_fts WHERE turns_fts MATCH 'tender'")->fetchAll(PDO::FETCH_ASSOC);
    expect(count($ftsResults))->toBeGreaterThanOrEqual(1);

    File::delete($dbPath);
});

it('exports sessions to JSON Lines format', function () {
    $exporter = app(SessionExportService::class);
    $jsonlPath = $exporter->exportToJsonl();

    expect(File::exists($jsonlPath))->toBeTrue();

    $lines = file($jsonlPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    expect(count($lines))->toBe(2);

    $session1Data = json_decode($lines[0], true);
    expect($session1Data['session_id'])->toBe($this->session1->id)
        ->and($session1Data['provider'])->toBe('tadbir')
        ->and($session1Data['decision_marker'])->toBeTrue()
        ->and(count($session1Data['turns']))->toBe(2)
        ->and($session1Data['turns'][1]['tool_calls'][0]['name'])->toBe('search_mail');

    File::delete($jsonlPath);
});

it('executes artisan session:export command successfully', function () {
    $this->artisan('session:export --format=all')
        ->assertSuccessful()
        ->expectsOutputToContain('Export completed successfully');
});

it('executes artisan session:export with --stdout option', function () {
    $this->artisan('session:export --stdout')
        ->assertSuccessful();
});

it('exports a single filtered session when --session is specified', function () {
    $exporter = app(SessionExportService::class);
    $jsonlPath = $exporter->exportToJsonl($this->session1->id);

    $lines = file($jsonlPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    expect(count($lines))->toBe(1);

    $data = json_decode($lines[0], true);
    expect($data['session_id'])->toBe($this->session1->id);

    File::delete($jsonlPath);
});

it('downloads exported db file through authenticated web endpoint', function () {
    $response = $this->actingAs($this->user)->get('/admin/sessions/export/db');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/x-sqlite3');
});

it('downloads exported jsonl file through authenticated web endpoint', function () {
    $response = $this->actingAs($this->user)->get('/admin/sessions/export/jsonl');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/x-ndjson');
});

it('exports all formats when format option is all', function () {
    $this->artisan('session:export', ['--format' => 'all'])
        ->assertSuccessful();
});
