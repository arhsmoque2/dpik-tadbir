# Tadbir Control Plane (`tadbir`)

> **Deterministic runtime control plane, snip output filtering, and state-first quality harness for DPIK Tadbir.**

---

## 1. The Problem We Solved

Analysis of **54 historical agent sessions** across Kimi, Claude Code, Codex, and Antigravity operating on `dpik-tadbir` revealed severe operational token churn:

```
┌───────────────────────────────────────┬─────────────────────────────────────────────────────────────┐
│ Historical Anti-Pattern               │ Empirical Telemetry Impact                                  │
├───────────────────────────────────────┼─────────────────────────────────────────────────────────────┤
│ 1. CI/CD Busy-Waiting Loops           │ 59% of total token spend (10.35 MB) was spent polling       │
│                                       │ `gh run view` in tight 15-second loops.                     │
├───────────────────────────────────────┼─────────────────────────────────────────────────────────────┤
│ 2. Cold-Start Reconnaissance          │ 893 repeated file read chains across cold boots to re-learn │
│                                       │ git branch, CI state, and toolchain paths (~4,500 tokens).   │
├───────────────────────────────────────┼─────────────────────────────────────────────────────────────┤
│ 3. Context Pollution                  │ Raw `pest --ci` and `gh run` dumps emit 150–200 lines of    │
│                                       │ passing checkmarks, ANSI codes, and Node deprecations.      │
├───────────────────────────────────────┼─────────────────────────────────────────────────────────────┤
│ 4. The "Verdict vs State" Trap        │ Tools emitted `"overall": "pass"`. Reviewing agents could   │
│                                       │ not verify whether 0 or 187 tests ran, forcing re-runs.     │
└───────────────────────────────────────┴─────────────────────────────────────────────────────────────┘
```

`tadbir` resolves all four anti-patterns by composing existing toolchains into a single-shot, machine-parseable control plane.

---

## 2. Command Surface & Decision Matrix

| Trigger / Agent Objective | Canonical Command | Operational Guarantee |
| :--- | :--- | :--- |
| **Session Cold-Start / Baseline Recon** | `python tools/tadbir.py status` | 1-turn verified state: git, branch-scoped CI, toolchains, manifest validation, snip state, continuity. |
| **Pre-Push Quality Gate (fast subset)** | `python tools/tadbir.py gate` | Runs Pint, PHPStan (level per `phpstan.neon`), Pest; parses output into metrics. Exits `0` on pass. `composer check:full` is the authoritative pre-merge gate. |
| **Triage Failing PR (e.g. PR #35)** | `python tools/tadbir.py pr [N]` | Single-shot PR status, failing CI step, and exact missing coverage diff lines. |
| **Check Remote CI Without Polling** | `python tools/tadbir.py ci-wait` | Non-blocking single-shot CI run status check. Never busy-polls. A still-running CI exits `0`. |
| **Pest Failure Batch Analysis** | `python tools/tadbir.py test-triage` | Clusters test failures by parent directory and common exception root cause. |
| **Register snip filters (first checkout / after editing them)** | `python tools/tadbir.py snip-setup` | Adds `.snip/filters` to the global snip config, `snip trust`s it, runs `snip verify`. |

---

## 3. Architecture & Design Principles

### A. Zero-Runtime-Dependency Python stdlib (`tools/tadbir.py`)
- Runtime uses the **Python standard library only** (`argparse`, `json`, `subprocess`, `re`, `pathlib`). `tools/tadbir.py` is a stdlib shim onto the `tools/tadbir_cli/` package.
- Managed via `uv` in `tools/tadbir_cli/` with strict Mypy typing, Ruff, and ~84% Pytest line coverage (60 tests). Dev-only deps: `pytest`, `mypy`, `ruff`, `pyyaml`.
- No runtime package dependencies — runs on Windows hosts, Linux containers, and GitHub Actions.

### B. High-Signal Snip Output Filters (`.snip/filters/*.yaml`)

`gate` / `test-triage` parse tool output into metrics directly (the JSON is the compression). These filters compress **raw** commands run through snip's PreToolUse hook; `pr` / `ci-wait` also route their `gh run` calls through snip. Each filter has inline `tests:` (14 total, run by `snip verify` / `python tools/tadbir.py snip-verify`).

- `pest.yaml` (`command: pest`): keeps `Failed asserting` / `Expected` / `Actual`, `at file:line`, the `▕` snippet lines and the `Tests:` / `Duration:` summary; drops `✓` and `PASS` lines.
- `phpstan.yaml` (`command: phpstan`): keeps error `file` + `line:message`, `Found N errors` / `[OK]`, and memory-crash `--memory-limit` hints; drops box-drawing and the progress meter.
- `pint.yaml` (`command: pint`): keeps the `⨯ file` list and the `FAIL` summary; drops progress dots.
- `gh-run.yaml` (`gh run`, not `--log-failed`): keeps run/job/step status + annotations; strips Node-deprecation spam. Overrides the built-in.
- `gh-run-log-failed.yaml` (`gh run --log-failed`): strips the `job⇥step⇥timestamp` prefix + runner boilerplate; keeps test failures, PHPStan errors, the diff-cover missing-lines report.
- `artisan-migrate.yaml` (`php artisan`, requires `migrate`): collapses `DONE` rows to a count; keeps `FAIL` / `SQLSTATE` verbatim.

**Setup (once per machine):** `python tools/tadbir.py snip-setup` — the current snip release loads a project filter dir only when it is in the global `~/.config/snip/config.toml` `filters.dir` array **and** trusted. `snip-setup` does both idempotently (absolute path, no `${env.PWD}`).

### C. State > Verdict (Prime Directive 5)
`tadbir` **never emits human verdicts** like `"overall": "pass"` or `"All tests passed"`. It emits pure machine metrics:
```json
{
  "exit_code": 0,
  "scope": "fast subset (Pint, PHPStan, Pest)",
  "gates": {
    "phpstan": { "level": 8, "error_count": 0, "errors": [] },
    "pest": { "total_tests": 187, "passed": 187, "failed": 0, "assertions": 860 }
  }
}
```
The reviewing agent inspects `total_tests` and `assertions` to draw its own conclusion.

---

## 4. Governing Research & Audit Documentation

For auditors, reviewers, and agents seeking empirical evidence and architectural rationale:

- **Telemetry & Churn Analysis**: [`docs/research/PR-006-session-telemetry-and-token-churn-analysis.md`](../../docs/research/PR-006-session-telemetry-and-token-churn-analysis.md)
- **Reproducible Probes & Snip Architecture**: [`docs/research/PR-007-reproducible-probes-and-snip-output-filtering-architecture.md`](../../docs/research/PR-007-reproducible-probes-and-snip-output-filtering-architecture.md)
- **Empirical Snip Filter Validation**: [`docs/research/PR-008-snip-filter-empirical-validation-and-signal-preservation.md`](../../docs/research/PR-008-snip-filter-empirical-validation-and-signal-preservation.md)
- **Architectural Decision Record**: [`docs/adr/ADR-030-tadbir-runtime-control-plane-snip-output-filtering-and-state-doctrine.md`](../../docs/adr/ADR-030-tadbir-runtime-control-plane-snip-output-filtering-and-state-doctrine.md)
- **Mandatory Cold-Start Protocol**: [`AGENTS.md`](../../AGENTS.md)
