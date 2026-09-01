# ADR-030: Tadbir Runtime Control Plane, Snip Output Filtering, and State-First Doctrine

- **Status**: Accepted
- **Date**: 2026-09-02
- **Author**: Antigravity Agent & System Architecture
- **Context**: dpik-tadbir developer and agent operational telemetry revealed that over 59% of session tokens were churned in recursive CI polling loops, noisy unfiltered test runs, and repeated context reconstruction due to lack of verified, machine-parseable runtime state.

---

## 1. Problem Statement

1. **Context Pollution**: Running raw `vendor/bin/pest`, `gh run view`, and `phpstan` in long agent sessions emits hundreds of lines of visual noise (passing checkmarks, ANSI escape codes, deprecation warnings), overwhelming the agent's context window.
2. **The "Verdict vs State" Trap**: Prior scripts emitted boolean verdicts like `"PASS"` or `"All tests passed"`. Reviewing agents could not ascertain whether 0 tests ran or 187 tests ran, forcing them to re-run raw tools manually.
3. **Cold-Start Friction**: Agents booting into `dpik-tadbir` had no single entry point to discover git state, CI health, toolchain binaries, and PR triage without executing 10-15 ad-hoc discovery commands.
4. **Cloud Sandbox Incompatibility**: PowerShell-only scripts broke in ephemeral Linux containers (GitHub Codespaces, Claude Code web, Docker).

---

## 2. Decision & Architecture

### A. Zero-Runtime-Dependency Python Control Plane (`tools/tadbir.py` & `tools/tadbir_cli`)
- Implemented in Python >=3.11 using **standard library only at runtime** (`argparse`, `json`, `subprocess`, `re`, `pathlib`). `tools/tadbir.py` is a stdlib shim onto the `tools/tadbir_cli/` package.
- Managed via `uv` package layout in `tools/tadbir_cli/` with strict Mypy type-safety, Ruff linting, and ~84% Pytest line coverage (60 tests). Dev-only deps: `pytest`, `mypy`, `ruff`, `pyyaml` (snip-filter structural validation).
- Runs on Windows host, Linux containers and GitHub Actions via `python tools/tadbir.py <cmd>` or `composer tadbir:<cmd>`. On Windows-native shells use `php vendor/bin/<tool>`; the `pest`/`pint`/`phpstan` snip filters match bare-name invocations (`vendor/bin/pest`) run through a shell that honours the shebang.

### B. Project-Local High-Signal Snip Filters (`.snip/filters/*.yaml`)

`gate` and `test-triage` parse tool output into metrics directly — the JSON payload is the compression, so they do not route through snip. The filters compress **raw** `pest` / `phpstan` / `pint` / `gh run` / `php artisan migrate` output when an agent runs those commands directly through snip's PreToolUse hook (the measured anti-pattern in [PR-006](../research/PR-006-session-telemetry-and-token-churn-analysis.md)); `pr` and `ci-wait` also route their `gh run` subprocess calls through snip.

- `pest.yaml` (`command: pest`): keeps `Failed asserting` / `Expected` / `Actual` diffs, `at file:line` pointers, the `▕` code-snippet lines and the `Tests:` / `Duration:` summary; drops passing `✓` lines and `PASS` headers.
- `phpstan.yaml` (`command: phpstan`): keeps error `file` + `line:message`, the `Found N errors` / `[OK]` line and memory-crash remediation hints; drops the box-drawing borders and progress meter.
- `pint.yaml` (`command: pint`): keeps the changed/failed file list and the `FAIL` summary; drops the per-file progress dots.
- `gh-run.yaml` (`command: gh run`, no `--log-failed`): keeps run/job/step status and annotations; strips Node deprecation spam. Overrides the built-in `gh-run`.
- `gh-run-log-failed.yaml` (`command: gh run --log-failed`): strips the `job⇥step⇥timestamp` prefix and runner boilerplate; keeps test failures, PHPStan errors and the diff-cover missing-lines report.
- `artisan-migrate.yaml` (`command: php artisan`, requires `migrate`): collapses the per-migration `DONE` rows into a single count; keeps `FAIL` / `SQLSTATE` / rollback lines verbatim.

Each filter carries inline `tests:` blocks (14 total). The current snip release loads a project filter directory only when it is listed in the **global** `~/.config/snip/config.toml` `filters.dir` array **and** trusted (`snip trust`); `python tools/tadbir.py snip-setup` does both idempotently using this repo's absolute path (no `${env.PWD}` dependency), then runs `snip verify`. `snip-verify` re-runs the inline tests on demand. When snip is absent the filters are inert and versioned in the repo — nothing breaks.

### C. State > Verdict Doctrine (Prime Directive 5)
- All commands output **pure empirical state**:
  - `total_tests`, `passed`, `failed`, `skipped`, `assertions`.
  - `error_count`, `violations_count`, `exit_code`.
- Zero fabricated `"overall": "pass"` or `"status": "pass"` verdicts. The reviewing agent inspects the state metrics and draws its own verified conclusions.

### D. Output Optimization & Path Compression
- Declares `root` once at the top level.
- Normalizes all paths to relative POSIX format (`app/...`, `tests/...`).
- Groups test failures by parent directory in `test-triage` to eliminate repetitive directory tokens.
- Emits token-efficient JSON Decision Matrix on `--help` (~180 tokens).

---

## 3. Command Surface

| Command | Purpose | Input / Flags | Output Guarantee |
| :--- | :--- | :--- | :--- |
| `tadbir status` | 1-turn baseline reconnaissance | `--cached` | Live git, branch-scoped CI status, toolchain, manifest check, snip state, continuity pointers |
| `tadbir gate` | Local preflight (fast subset) | — | Pint, PHPStan (level per `phpstan.neon`), Pest state with exact assertion & test counts |
| `tadbir pr [N]` | Single-shot PR triage | `[number]` | Branch, CI run ID, failing job, and missing diff-cover lines |
| `tadbir ci-wait` | Non-blocking CI check | `--branch` | Single-shot remote CI run status (never busy-polls) |
| `tadbir test-triage` | Batch failure clustering | None | Collapses failures by directory and common exception |
| `tadbir snip-setup` | Register + trust snip filters | — | Config action, trust result, `snip verify` per-filter pass counts |
| `tadbir snip-verify` | Re-run filter inline tests | — | Per-filter pass counts, invalid-filter list |

`gate` is the **fast subset**. `composer check:full` (FilaCheck, composer-unused, diff-cover ≥ 90) stays the authoritative pre-merge gate; CI runs the full set.

Exit codes: `0` = pass / healthy / **CI still running** (an unfinished PR is not a failure); `1` = a failing conclusion or an unparseable state.

---

## 4. Consequences & Verification

- **Token Economy**: cold-start recon collapses ~15–30 discovery calls into one `status` JSON. Measured raw→snipped: `gh run view` 30→15 lines; `pest --ci` (all green) 291→2 lines. Filter signal-preservation is guarded by 14 inline `snip verify` tests — see [PR-008](../research/PR-008-snip-filter-empirical-validation-and-signal-preservation.md).
- **False-Positive Prevention**: transparent `total_tests` / `error_count` prevent empty-run false positives; `status` scopes the CI probe to the current branch so it cannot report an unrelated PR's green run.
- **No silent degradation**: when snip is absent, filters are inert and `snip_runner` falls back to raw execution; `gate` never depended on snip. `status.snip.action` tells a cold agent when to run `snip-setup`.
- **Portability**: runs on Windows host and Linux with zero runtime Python dependencies. Project-filter loading needs the one-time `snip-setup` step per machine (global-config + trust-store), documented in `.snip/config.toml`.
