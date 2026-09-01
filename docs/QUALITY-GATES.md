# DPIK Tadbir: Quality Gates & Pre-Merge Gate Suite

Modeled directly on the proven multi-tier quality gates from **`ARH-FNB-Beelal-Coffee`** and **`ARH-URUS`**, every pull request must pass 5 automated verification gates before merging (governed by [`ADR-015`](adr/ADR-015-quality-gates-e2e-testing-and-ai-observability-resilience.md), [`ADR-016`](adr/ADR-016-ci-cd-quality-hardening-operational-blindspot-remediation.md), and [`ADR-029`](adr/ADR-029-hermetic-io-stream-and-socket-mocking-architecture.md)).

---

## Gate 1: Static Analysis, Typing & Documentation Hygiene

- **Code Style (Laravel Pint)**: Zero formatting errors (`./vendor/bin/pint --test`).
- **Static Typing (Larastan / PHPStan)**: Level 8 strictness (`./vendor/bin/phpstan analyse --level=8 --memory-limit=1G`).
- **Filament v4 AST Linter (FilaCheck)**: 17/17 rules passed across `app/Filament`, preventing deprecated v3 methods (`./vendor/bin/filacheck app/Filament`).
- **Documentation Linting (`markdownlint-cli2`)**: Zero markdown syntax errors or broken heading IDs across `docs/` and `adr/` (`npx --yes markdownlint-cli2 "**/*.md" "#node_modules"`).
- **Spelling & Lexicon (`cspell`)**: Zero spelling errors or typos across specs, comments, and schemas (`npx --yes cspell --no-progress "**/*.md" "docs/**/*.json"` governed by `.cspell.json`).
- **Dead Code Audit (`composer-unused`)**: Zero unused composer dependencies or orphaned service bindings (`./vendor/bin/composer-unused`).

---

## Gate 2: Security, Privacy & Write-Safety Preflight

- **Secret Preflight Scanner (`gitleaks`)**: Automated preflight scan ensuring zero API keys, Graph tokens, or SSH credentials exist in committed code, fixtures, or environment examples.
- **Environment & Variable Contract Verification**: Verifies compliance with [`docs/ENVIRONMENT.md`](ENVIRONMENT.md), ensuring all cloud run variables, database endpoints, and AI provider credentials conform to the zero-leak storage tiering model.
- **Fail-Closed Security Preflight Test Suite**: Executed directly inside the Gate 2 CI job before static analysis or feature testing:
  - **PII Storage Sanitization Gate (`PiiStorageSanitizationTest`)**: Asserts that all prompts, responses, errors, metadata, and log messages are strictly redacted, ensuring zero plaintext Malaysian NRICs, credit cards, or secret tokens are stored or logged.
  - **Registration Whitelist & Sovereign Isolation (`RegistrationWhitelistTest`)**: 100% test verification asserting non-whitelisted emails are rejected with 403 Forbidden, and whitelisted registered accounts receive fully isolated sovereign workspaces ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)).
  - **Personal Privacy Policy Isolation (`PersonalNotePolicy`, `PersonalTaskPolicy`, `ChatSessionPolicy`)**: 100% test verification in `tests/Feature/Notes/PersonalNotePolicyTest.php`, `tests/Feature/Tasks/PersonalTaskPolicyTest.php`, and `tests/Feature/Chat/ChatSessionPolicyTest.php` ensuring records strictly enforce `auth()->id()` user scoping.
  - **Fail-Closed Write Confirmation (`WriteSafetyProposalTest`)**: Asserts that all write-mutation tools (`OutlookCreateDraftTool`, `OutlookReplyTool`, `OutlookForwardTool`) reject dispatch if an approval token is missing, forged, or expired ([`ADR-007`](adr/ADR-007-write-safety-human-in-the-loop-approval-gates.md)).

---

## Gate 3: Hermetic Test Suite, AI Resilience & Diff-Cover

- **Hermetic SQLite Sandbox**: 100% passing tests under in-memory SQLite (`./vendor/bin/pest --coverage-clover coverage.xml --parallel`).
- **Incremental Diff-Cover Gate**: Minimum **90% branch coverage** on modified lines in all PRs (`uvx diff-cover coverage.xml --compare-branch origin/main --fail-under=90`).
- **Hermetic I/O Stream & Socket Mocking (`ADR-029`)**: All services interacting with native network sockets (`stream_socket_client`, `fsockopen`) must use `php-mock/php-mock-mockery` and in-memory streams (`php://temp`) to test connection failure, SSL timeouts, and protocol error branches hermetically without unmocked network egress.
- **Filament v4 & Livewire Action Testing**: Standalone Filament pages (`ExecutiveSettings`) must test interactive actions (`call()`, `mountAction()`) via Pest's Livewire harness to maintain 90%+ branch coverage.
- **Multi-Provider AI Resilience (`AgentServiceResilienceTest`)**: 100% test verification asserting that primary provider rate limits automatically trigger fallback to secondary models (Anthropic $\to$ Gemini), and complete provider failures degrade gracefully into user-friendly notices without throwing HTTP 500 errors.
- **Database N+1 Query & Eloquent Hygiene Gate (`FilamentResourcesTest`)**:
  - `DatabaseQueryCounter::assertMaxQueries()` ensures all resource listings execute with $O(1)$ constant query bounds.
  - `Model::preventLazyLoading` and `Model::preventSilentlyDiscardingAttributes` active in `AppServiceProvider` to fail immediately on un-eager loaded relations or un-fillable attributes in testing/local environments.
- **Exhaustive Mutation Testing**: Decoupled from the PR path to preserve velocity (<3 min runs); scheduled as a weekly deep audit job (`.github/workflows/weekly-quality-audit.yml`) running `./vendor/bin/pest --mutate --covered-only --parallel --min=70`.
- **Tier 1 Mission-Critical Modules (95–100% target)**:
  - `App\Services\Ai\AgentService`
  - `App\Services\Ai\AiRunRecorder`
  - `App\Services\Ai\AntiHallucinationGuard`
  - `App\Services\Ai\CostCalculator`
  - `App\Services\Ai\LlmGatewayService`
  - `App\Services\Ai\PiiDetector`
  - `App\Services\Auth\RegistrationWhitelistService`
  - `App\Http\Middleware\RegistrationWhitelistMiddleware`
  - `App\Services\Mcp\OutlookMcpBridge`
  - `App\Services\Mcp\MailDiagnosticService`
  - `App\Services\Memory\MemoryRetrievalService`
  - `App\Services\Audit\ActionMemoryService`
  - `App\Mcp\ToolRegistry`
  - `App\Mcp\Tools\Outlook\OutlookCreateDraftTool`
  - `App\Mcp\Tools\Outlook\OutlookReplyTool`
  - `App\Mcp\Tools\Outlook\OutlookForwardTool`
  - `App\Policies\PersonalNotePolicy`
  - `App\Policies\PersonalTaskPolicy`
  - `App\Policies\ExecutivePresetPolicy`
  - `App\Policies\ChatSessionPolicy`

---

## Gate 4: Playwright E2E, Visual & Accessibility QA (Manual Baseline Mode)

- **Manual / Operator-Triggered Runner (`pnpm test:e2e`)**: Decoupled from automated CI PR blocks to prevent flaky headless webServer timeouts and false positives during active iteration. Kept for manual validation, visual snapshot baselines, and local/on-demand rehearsal until formal baseline approval.
- **Headless Playwright Chromium & Mobile Runner**: Configured via `playwright.config.ts` running against a dedicated, persistent file-backed testing server (`php artisan serve --env=testing --port=8000`):
  - **Global Auth Setup (`auth.setup.ts`)**: Seeds deterministic test user (`DatabaseSeeder`) and generates reusable `playwright/.auth/user.json` session state.
  - **Auth & Session Journey (`01-auth-journey.spec.ts`)**: Asserts login form, CSRF token validation, invalid credentials rejection, and successful authenticated redirection.
  - **Project Register Responsive Toolbar (`02-project-register-journey.spec.ts`)**: Asserts table column rendering, search inputs, seeded project data (`PC-2023-011`), and responsive toolbar action layout (zero horizontal viewport scroll on mobile displays).
  - **Livewire Copilot Drawer & Action Cards (`03-copilot-drawer-journey.spec.ts`)**: Asserts `data-copilot-trigger` render hook, `data-copilot-drawer` DOM hydration, presets ribbon ("Tender Review Brief"), and input textarea.
- **Automated Accessibility Audit (`04-visual-and-accessibility.spec.ts`)**:
  - `@axe-core/playwright` runs automated WCAG 2.1 Level AA conformance scans across Level A and Level AA rules without disabling color contrast.
  - Deep whole-site scans run weekly or on-demand via `webcrafts-studio/lens-for-laravel` (`php artisan lens:audit`).
- **Visual Regression Baselines**: Captures full-page viewport screenshots using `expect(page).toHaveScreenshot('admin-login.png', { maxDiffPixelRatio: 0.05, animations: 'disabled' })`.

---

## Gate 5: Safe Deployment Gating (`workflow_run`)

- **Zero Race Conditions**: Production deployment is **never** triggered directly by raw branch push.
- **Workflow Run Trigger**: Deploys execute strictly via `.github/workflows/deploy.yml` on `on: workflow_run: workflows: ["Quality Gate CI"]: types: [completed]` and assert `github.event.workflow_run.conclusion == 'success'` on the `main` branch.
- **Ordered Execution**: Neon unpooled direct connection executes `php artisan migrate --force` as an isolated Cloud Run job before traffic is routed to the new service revision.
