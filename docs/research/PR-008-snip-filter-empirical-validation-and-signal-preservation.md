# PR-008: Snip Filter Validation & Signal Preservation

> **Context**: how the `.snip/filters/*.yaml` in this repo are validated, and the signal each one is contracted to keep. Every claim here is reproducible from the repo — no hand-transcribed numbers.

---

## 1. How the filters are validated

Each filter carries inline `tests:` blocks (14 in total). They are run by snip's own
pipeline evaluator:

```bash
python tools/tadbir.py snip-verify      # or: snip verify   (from the repo root)
```

`snip verify` feeds each test's `input` through the real filter `pipeline` and does an
exact string compare against `expected` (trailing newline trimmed). Current state:

| Filter | Inline tests | Covers |
| :--- | :---: | :--- |
| `pest` | 2 | all-green run collapses to `Tests:` + `Duration:`; a failure keeps the `FAIL` header, `FAILED …`, `Failed asserting …`, `at file:line`, the `▕` snippet lines and the summary |
| `phpstan` | 3 | clean run → `[OK] No errors`; errors keep `file` + `line:message` + `Found N errors`; a memory crash keeps the `--memory-limit` remediation line |
| `pint` | 2 | clean check → `ok (no style changes)`; a failure keeps the `⨯ file` list and the `FAIL … style issues` summary |
| `gh-run` | 2 | a failing run keeps job/step status + the `--log-failed` hint; Node-deprecation lines are stripped |
| `gh-run-log-failed` | 2 | the `job⇥step⇥timestamp` prefix and runner boilerplate are stripped; the diff-cover `Missing lines …` report and a pest `FAILED …` survive |
| `artisan-migrate` | 3 | many `DONE` rows collapse to `applied: N migration(s)`; a `FAIL` keeps its `SQLSTATE` line; `Nothing to migrate` is kept |

The `tadbir-tooling` CI job runs `test_snip_filters.py`, which additionally
structurally validates every filter (required keys, known pipeline actions,
≥1 inline test) without needing the snip binary, and runs `snip verify` when the
binary is present.

## 2. Measured raw → snipped

Captured live against this repo (both re-runnable):

| Command | Raw lines | Snipped lines | Note |
| :--- | :---: | :---: | :--- |
| `gh run view <id>` (all green, 5 jobs) | 30 | 15 | annotations kept; `Triggered via` / repeated footer stripped |
| `vendor/bin/pest --ci` (187 passing) | 291 | 2 | `Tests:` + `Duration:` only; a failing run keeps the whole failure block (see the `pest` inline test) |

`phpstan` / `pint` / `gh-run-log-failed` / `artisan-migrate` compression is not
given a headline number here because it is entirely input-dependent (a clean run
is ~1 line either way; a 40-error run keeps 40 lines). The inline tests pin the
*shape* of the output, which is the property that matters.

## 3. Signal-retention invariants (enforced by the inline tests)

1. **Pest** — never strip `Failed asserting` / `Expected` / `Actual`, the `at file:line` pointer, or the `Tests:` count line.
2. **PHPStan** — never strip a memory-crash diagnostic or its `--memory-limit` remediation hint; never drop the `Found N errors` count.
3. **diff-cover** (`gh-run-log-failed`) — never strip the `Missing lines …` / `Coverage … below …` report.
4. **Exit codes** — snip always propagates the underlying tool's exit code unmasked; `snip_runner` additionally falls back to raw execution if a snip-wrapped invocation fails to exec (Windows shebang scripts).
5. **Absence is safe** — with snip not installed, every filter is inert and commands run raw.
