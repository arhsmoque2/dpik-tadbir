#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Gates the generated capability manifest against the human/ADR-owned
 * roadmap (docs/testing/capability-roadmap.json — the only hand-edited
 * file in this system) and fails on either direction of drift:
 *
 *  - approved in the roadmap, but no test declares it in code
 *      -> "declared but unbuilt" as far as the roadmap is concerned
 *  - approved in the roadmap, declared in code, but --verify-* found it
 *    failing
 *      -> declared as implemented, doesn't actually work
 *  - found declared in code with NO roadmap entry at all
 *      -> built without ever being approved — this is the "built but
 *         undeclared" direction; a capability appearing from nowhere is
 *         scope creep the roadmap never signed off on
 *
 * A `planned` or `deferred` roadmap entry places no requirement on code —
 * it's fine for it to be unbuilt, and fine (if unusual) for it to already
 * have a passing test ahead of being marked approved.
 *
 * Run generate.php first; this script only reads its output.
 */
const MANIFEST_PATH = __DIR__.'/../../docs/testing/capability-manifest.generated.json';
const ROADMAP_PATH = __DIR__.'/../../docs/testing/capability-roadmap.json';

/**
 * @return array<string, mixed>
 */
function loadJson(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "capabilities:diff: missing {$path}\n");
        exit(1);
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (! is_array($data)) {
        fwrite(STDERR, "capabilities:diff: {$path} is not valid JSON\n");
        exit(1);
    }

    return $data;
}

$manifest = loadJson(MANIFEST_PATH);
$roadmap = loadJson(ROADMAP_PATH);

$generated = $manifest['capabilities'] ?? [];
$declared = $roadmap['capabilities'] ?? [];

$violations = [];
$notes = [];

// Direction 1 & test-failure: every `approved` roadmap entry must exist in
// code, and if verification ran, must pass.
foreach ($declared as $key => $entry) {
    $state = $entry['state'] ?? 'planned';
    if ($state !== 'approved') {
        continue;
    }

    if (! isset($generated[$key]) || empty($generated[$key]['declared_in'])) {
        $violations[] = "APPROVED but not built: '{$key}' is approved in the roadmap but no test declares @capability({$key}) anywhere in the codebase.";

        continue;
    }

    $verified = $generated[$key]['verified'] ?? null;
    if ($verified === false) {
        $files = implode(', ', array_map(fn (array $d): string => "{$d['file']}:{$d['line']}", $generated[$key]['declared_in']));
        $violations[] = "APPROVED but failing: '{$key}' is approved and declared ({$files}), but its test does not pass.";
    }
}

// Direction 2: every capability found in code must have a roadmap entry.
foreach ($generated as $key => $entry) {
    if (empty($entry['declared_in'])) {
        continue;
    }
    if (! isset($declared[$key])) {
        $files = implode(', ', array_map(fn (array $d): string => "{$d['file']}:{$d['line']}", $entry['declared_in']));
        $violations[] = "UNDECLARED capability: '{$key}' is proven by a test ({$files}) but has no entry in docs/testing/capability-roadmap.json — add one (state: approved/planned/deferred) before this can land.";
    }
}

// Informational only: a `planned` capability that's already built. Good
// news, not a failure — but worth surfacing so the roadmap gets updated to
// `approved` and the gate actually protects it going forward.
foreach ($declared as $key => $entry) {
    $state = $entry['state'] ?? 'planned';
    if ($state === 'planned' && isset($generated[$key]) && ! empty($generated[$key]['declared_in'])) {
        $notes[] = "'{$key}' is marked 'planned' but a test already declares it — consider promoting it to 'approved' in the roadmap so it's actually gated.";
    }
}

echo 'Capability gate: '.count($generated).' declared in code, '.count($declared)." in roadmap.\n";

if ($notes !== []) {
    echo "\nNotes (non-blocking):\n";
    foreach ($notes as $note) {
        echo "  - {$note}\n";
    }
}

if ($violations !== []) {
    echo "\nVIOLATIONS:\n";
    foreach ($violations as $violation) {
        echo "  ✗ {$violation}\n";
    }
    echo "\n".count($violations)." violation(s). Fix these before committing/pushing.\n";
    exit(1);
}

echo "\nNo violations. Capability manifest is consistent with the roadmap.\n";
