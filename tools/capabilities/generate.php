#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generates docs/testing/capability-manifest.generated.json from the
 * codebase itself — never hand-edited. A capability is declared by placing
 * an `@capability(<key>)` comment directly above the test that proves it,
 * in either a Pest/PHPUnit test (tests/Feature/**\/*.php) or a Playwright
 * spec (tests/Browser/**\/*.spec.ts):
 *
 *   // @capability(chat.copilot-drawer-close)
 *   public function test_drawer_close_button_is_reachable(): void { ... }
 *
 *   // @capability(chat.copilot-drawer-close)
 *   test('AI Copilot drawer exposes a reachable close control', async ({ page }) => { ... });
 *
 * The marker must sit on the line immediately above the test declaration —
 * a marker that can't be matched to a test is a hard error, not a silent
 * skip, because a manifest that can mis-map is a manifest nobody can trust.
 *
 * Modes (combine as needed):
 *   (no flag)        static scan only — no test execution (fast, safe for
 *                    every pre-commit)
 *   --verify-php     additionally run each Pest/PHPUnit-backed capability's
 *                    test and record pass/fail
 *   --verify-browser additionally run each Playwright-backed capability's
 *                    test and record pass/fail (requires the app + browsers
 *                    already available — CI's navigation-hygiene job, not a
 *                    bare local pre-commit)
 *
 * Output is stamped with the exact commit the scan ran against, so a
 * consumer (a hook, CI, or an agent re-reading this later) can tell the
 * manifest is fresh rather than assuming it.
 */
const OUT_PATH = __DIR__.'/../../docs/testing/capability-manifest.generated.json';
const CAPABILITY_MARKER = '/@capability\(([a-z0-9][a-z0-9.\-]*)\)/i';

function fail(string $message): never
{
    fwrite(STDERR, "capabilities:generate: {$message}\n");
    exit(1);
}

function gitSha(): string
{
    $sha = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));

    return $sha !== '' ? $sha : 'unknown';
}

function isDirty(): bool
{
    $status = trim((string) shell_exec('git status --porcelain 2>/dev/null'));

    return $status !== '';
}

/**
 * @return list<string>
 */
function findFiles(string $root, string $extension): array
{
    if (! is_dir($root)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $files = [];
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() === $extension) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

/**
 * @return list<array{key: string, file: string, line: int, test_id: string, kind: string}>
 */
function scanFile(string $path, string $repoRoot): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fail("could not read {$path}");
    }

    $relative = ltrim(str_replace($repoRoot, '', $path), '/');
    $results = [];

    foreach ($lines as $i => $line) {
        if (! preg_match(CAPABILITY_MARKER, $line, $m)) {
            continue;
        }
        $key = $m[1];

        // Find the next non-blank line after the marker.
        $j = $i + 1;
        while ($j < count($lines) && trim($lines[$j]) === '') {
            $j++;
        }
        if ($j >= count($lines)) {
            fail("{$relative}:".($i + 1)." declares @capability({$key}) but no test declaration follows it");
        }
        $next = $lines[$j];

        if (str_ends_with($path, '.php')) {
            if (preg_match('/function\s+(\w+)\s*\(/', $next, $fm)) {
                $results[] = ['key' => $key, 'file' => $relative, 'line' => $i + 1, 'test_id' => $fm[1], 'kind' => 'pest-method'];

                continue;
            }
            if (preg_match('/^\s*(?:test|it)\(\s*[\'"]([^\'"]+)[\'"]/', $next, $tm)) {
                $results[] = ['key' => $key, 'file' => $relative, 'line' => $i + 1, 'test_id' => $tm[1], 'kind' => 'pest-title'];

                continue;
            }
        } elseif (str_ends_with($path, '.ts')) {
            if (preg_match('/test\(\s*[\'"]([^\'"]+)[\'"]/', $next, $tm)) {
                $results[] = ['key' => $key, 'file' => $relative, 'line' => $i + 1, 'test_id' => $tm[1], 'kind' => 'playwright-title'];

                continue;
            }
        }

        fail("{$relative}:".($i + 1)." declares @capability({$key}) but the next line doesn't look like a test declaration:\n    ".trim($next));
    }

    return $results;
}

function runPestMethod(string $repoRoot, string $file, string $method): bool
{
    $cmd = sprintf(
        'cd %s && ./vendor/bin/pest %s --filter=%s 2>&1',
        escapeshellarg($repoRoot),
        escapeshellarg($file),
        escapeshellarg($method)
    );
    exec($cmd, $output, $exitCode);

    return $exitCode === 0;
}

function runPlaywrightTitle(string $repoRoot, string $file, string $title): bool
{
    $cmd = sprintf(
        'cd %s && pnpm exec playwright test %s -g %s 2>&1',
        escapeshellarg($repoRoot),
        escapeshellarg($file),
        escapeshellarg($title)
    );
    exec($cmd, $output, $exitCode);

    return $exitCode === 0;
}

$repoRoot = realpath(__DIR__.'/../..');
if ($repoRoot === false) {
    fail('could not resolve repo root');
}

$verifyPhp = in_array('--verify-php', $argv, true);
$verifyBrowser = in_array('--verify-browser', $argv, true);

$phpFiles = findFiles($repoRoot.'/tests/Feature', 'php');
$tsFiles = array_filter(
    findFiles($repoRoot.'/tests/Browser', 'ts'),
    fn (string $f): bool => ! str_contains($f, '/support/')
);

$all = [];
foreach ([...$phpFiles, ...$tsFiles] as $file) {
    foreach (scanFile($file, $repoRoot) as $entry) {
        $all[$entry['key']][] = $entry;
    }
}

$capabilities = [];
foreach ($all as $key => $entries) {
    $verified = null;
    foreach ($entries as $entry) {
        $result = null;
        if ($entry['kind'] === 'pest-method' && $verifyPhp) {
            $result = runPestMethod($repoRoot, $entry['file'], $entry['test_id']);
        } elseif ($entry['kind'] === 'pest-title' && $verifyPhp) {
            $result = runPestMethod($repoRoot, $entry['file'], $entry['test_id']);
        } elseif ($entry['kind'] === 'playwright-title' && $verifyBrowser) {
            $result = runPlaywrightTitle($repoRoot, $entry['file'], $entry['test_id']);
        }
        if ($result !== null) {
            // A capability with multiple declaring tests is verified only if
            // every one of them passes.
            $verified = $verified === false ? false : $result;
        }
    }
    $capabilities[$key] = [
        'declared_in' => array_map(
            fn (array $e): array => ['file' => $e['file'], 'line' => $e['line'], 'test' => $e['test_id'], 'kind' => $e['kind']],
            $entries
        ),
        'verified' => $verified,
    ];
}
ksort($capabilities);

$manifest = [
    'generated_at' => gmdate('c'),
    'source_sha' => gitSha(),
    'source_dirty' => isDirty(),
    'mode' => [
        'verify_php' => $verifyPhp,
        'verify_browser' => $verifyBrowser,
    ],
    'capabilities' => $capabilities,
];

file_put_contents(OUT_PATH, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

fwrite(STDERR, 'capabilities:generate: '.count($capabilities).' capabilities found, written to '.basename(OUT_PATH)."\n");
