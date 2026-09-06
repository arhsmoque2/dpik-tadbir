# Weekly Quality & Mutation Audit — Disabled, Findings for Revival

**Status**: `.github/workflows/weekly-quality-audit.yml`'s `schedule` trigger was
removed on 2026-09-06. `workflow_dispatch` is kept so the fixes below can be
verified by hand before the cron is restored. Do not just uncomment the
schedule — confirm both jobs actually run green first (see "How to revive"
at the bottom).

## Why it was disabled

Both jobs in the workflow failed on their most recent scheduled run
(`main`@`1f88411`, run
[34005442875](https://github.com/arhsmoque2/dpik-tadbir/actions/runs/34005442875),
2026-09-06 02:02 UTC) — and on inspection, neither failure is an app
regression. Both are CI-configuration bugs in the workflow itself. Being
non-blocking (by design — this is the decoupled weekly gate, not the
merge-blocking `ci.yml`) meant nobody noticed the audit had stopped
producing real signal.

## Finding 1 — Deep Accessibility Audit (Lens for Laravel)

**Job**: `accessibility-deep-audit`
**Symptom**:

```
ERROR  Scan failed: Failed to run axe-core scan: ... Error: Cannot find module 'puppeteer'
Require stack:
- vendor/spatie/browsershot/bin/browser.cjs
```

**Root cause**: the job's "Install Puppeteer Dependencies for Browsershot"
step only runs `sudo apt-get install -y chromium-browser` — a system
Chromium binary. It never installs the `puppeteer` **npm package**, which is
what `vendor/spatie/browsershot/bin/browser.cjs` actually `require()`s at
runtime to drive that binary. The step name says "Puppeteer Dependencies"
but only ever installed the browser, not Puppeteer itself. This has
presumably never worked since the job was added — it isn't a regression
from a recent change.

**Recommended fix** — in `.github/workflows/weekly-quality-audit.yml`,
`accessibility-deep-audit` job, replace:

```yaml
      - name: Install Puppeteer Dependencies for Browsershot
        run: sudo apt-get update && sudo apt-get install -y chromium-browser
```

with something that installs the npm package and points Browsershot at the
already-present system Chromium (avoids Puppeteer's own bundled-Chromium
download, which is slow and occasionally blocked on GitHub's runners same
as it was in the local sandbox this session):

```yaml
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: 22

      - name: Install Puppeteer (Browsershot's actual runtime dependency)
        run: |
          npm install -g puppeteer
          sudo apt-get update && sudo apt-get install -y chromium-browser
```

and set `PUPPETEER_EXECUTABLE_PATH` (or Browsershot's
`->setChromePath()`/`services.browsershot.chrome_path` config, whichever
this repo already exposes — check `config/` and how `App\...\LensAudit` or
`php artisan lens:audit` resolves it) to the installed
`chromium-browser` binary, so Puppeteer doesn't also try to download its
own. Verify locally isn't possible in a sandbox without Chromium+Puppeteer
either — confirm via `workflow_dispatch` on a real runner before
re-enabling the cron.

## Finding 2 — Exhaustive Mutation Testing Gate (`pest --mutate`)

**Job**: `mutation-audit`
**Symptom**:

```
ERROR  Mutation testing requires the usage of the `covers()` function or the `mutates()` function.
```

231 tests passed first (the normal Pest suite runs fine), then the
mutation step itself refused to run at all.

**Root cause**: `./vendor/bin/pest --mutate --covered-only --parallel
--min=70` requires at least one test file to declare which class(es) it
covers via Pest's `covers(SomeClass::class)` (or `mutates(...)`) helper.
Nothing in `tests/` currently declares either — so the mutation engine has
zero scope to mutate and fails immediately, before generating a single
mutant. This gate has likely never actually mutated anything since it was
added; CURRENT_STATE.md's "exhaustive mutation testing... decoupled to
weekly scheduled audits" describes intent, not a working gate.

**Recommended fix**: this needs a real scoping decision, not just a config
tweak — `--covered-only` mutates only code that has coverage, so the
missing piece is telling Pest which classes each test **covers**. Two
viable approaches:

1. **Targeted** (recommended to start): add `covers(SomeClass::class);` to
   the top of a handful of the highest-value, already-well-tested classes
   first (e.g. `App\Services\Ai\LlmGatewayService`,
   `App\Services\Mail\MailBridge`, `App\Services\WriteSafety\*` — the
   write-safety/approval-gate code ADR-007 is built around is the highest
   payoff target for mutation testing specifically). Confirms the mechanism
   works end-to-end on real code before expanding scope.
2. **Blanket**: `pest --mutate --parallel --everything` mutates all
   coverable code without requiring per-file `covers()` — much slower, and
   `--min=70` may not be realistic repo-wide on the first real run (the
   flag was presumably chosen aspirationally, never validated against an
   actual score). If going this route, drop `--min=70` to a discovered
   baseline first, then ratchet it up in a follow-up once a real score
   exists — don't guess a threshold before you've seen a single real run
   pass or fail on it.

Either way: get one real, green (or honestly-scored) run via
`workflow_dispatch` before restoring the schedule. A `--min` threshold
nobody has ever seen pass is not a gate, it's a coin flip.

## How to revive

1. Fix Finding 1, fix Finding 2 (pick an approach above).
2. Push to a branch, trigger the workflow manually
   (`workflow_dispatch`) against that branch or `main`, and confirm **both**
   jobs go green for real — not just "doesn't error", actually read the
   Lens audit output and the mutation score.
3. Uncomment the `schedule:` block this file's header comment shows, in
   `.github/workflows/weekly-quality-audit.yml`.
4. Update this file's `Status` line to note the revival date and PR.
