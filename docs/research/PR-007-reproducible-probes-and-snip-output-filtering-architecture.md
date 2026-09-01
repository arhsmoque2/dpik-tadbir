# PR-007: Reproducible Probes & Snip Output Filtering Architecture

> **Context**: Architectural design document integrating `snip.exe` output compression and deterministic execution verification into `tadbir` CLI.

---

## 1. The Context Pollution Problem

Agents distrust static declarations unless backed by live, witnessed command execution. But running raw commands creates severe context pollution:
- A single `vendor/bin/pest --ci` run dumps ~180 lines.
- A `gh run view <id>` dumps ~120 lines (mostly repeated Node.js deprecation notices).
- A `phpstan analyse` emits 40–80 lines even on clean runs.

Over a session with 5 gate runs, 3 CI checks, and 10 git probes, **over 2,000 lines of visual noise** occupy the agent's context window.

---

## 2. Three-Layer Architecture

```
┌────────────────────────────────────────────────────────────┐
│  Layer 3: tadbir Control Plane (`tools/tadbir.py`)          │
│  Orchestrates multi-tool probes, parses output → JSON.      │
│  status, gate, pr, ci-wait, test-triage, snip-setup/-verify │
│  gate/test-triage: the JSON IS the compression (no snip).   │
│  pr/ci-wait: route their `gh run` calls through snip.       │
├────────────────────────────────────────────────────────────┤
│  Layer 2: snip filters (`.snip/filters/*.yaml`)             │
│  Compress RAW pest/phpstan/pint/gh-run/artisan-migrate      │
│  output when run through snip's PreToolUse hook.            │
│  pest, phpstan, pint, gh-run, gh-run-log-failed,           │
│  artisan-migrate — each with inline `tests:` (14 total).    │
├────────────────────────────────────────────────────────────┤
│  Layer 1: Real toolchains (vendor/bin/*, gh, git)          │
│  Actual execution — verifiable exit codes and metrics      │
└────────────────────────────────────────────────────────────┘
```

Layer 2 is optional: with snip absent the filters are inert and every command
runs raw. It needs the one-time `python tools/tadbir.py snip-setup` per machine
(registers `.snip/filters` in the global snip config + trusts it).

---

## 3. Context Budget

`tadbir gate` returns one JSON object (~15–40 lines) regardless of how much the
tools printed, because it parses `pest` / `phpstan` / `pint` output into
`passed` / `failed` / `error_count` / `violations_count` metrics. That is the
compression for the gate path — snip is not involved.

The snip filters matter for the **raw** commands agents type directly. Measured
against this repo (re-runnable):

| Command | Raw lines | Snipped lines |
| :--- | :---: | :---: |
| `gh run view <id>` (all green, 5 jobs) | 30 | 15 |
| `vendor/bin/pest --ci` (187 passing) | 291 | 2 |

Input-dependent filters (`phpstan`, `pint`, `gh-run-log-failed`,
`artisan-migrate`) are validated for output *shape* by 14 inline `snip verify`
tests rather than a headline ratio — see
[PR-008](PR-008-snip-filter-empirical-validation-and-signal-preservation.md).
