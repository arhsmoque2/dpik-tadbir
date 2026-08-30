# DPIK Tadbir: Quality Gates & Pre-Merge Gate Suite

Modeled directly on the proven multi-tier quality gates from **`ARH-FNB-Beelal-Coffee`** and **`ARH-URUS`**, every pull request must pass 5 automated verification gates before merging.

---

## Gate 1: Static Analysis, Typing & Documentation Hygiene
- **Code Style (Laravel Pint)**: Zero formatting errors (`./vendor/bin/pint --test`).
- **Static Typing (Larastan / PHPStan)**: Level 8 strictness (`./vendor/bin/phpstan analyse --level=8 --memory-limit=1G`).
- **Filament v4 AST Linter (FilaCheck)**: 17/17 rules passed across `app/Filament`, preventing deprecated v3 methods (`./vendor/bin/filacheck app/Filament`).
- **Documentation Linting (`markdownlint-cli2`)**: Zero markdown syntax errors or broken heading IDs across `docs/` and `adr/` (`npx --yes markdownlint-cli2 "**/*.md" "#node_modules"`).
- **Spelling & Lexicon (`cspell`)**: Zero spelling errors or typos across specs, comments, and schemas (`npx --yes cspell --no-progress "**/*.md" "docs/**/*.json"` governed by `.cspell.json`).
- **Dead Code Audit (`composer-unused`)**: Zero unused composer dependencies or orphaned service bindings (`./vendor/bin/composer-unused`).

---

## Gate 2: Security, Privacy & Write-Safety Invariants
- **Secret Preflight Scanner (`gitleaks`)**: Automated preflight scan ensuring zero API keys, Graph tokens, or SSH credentials exist in committed code, fixtures, or environment examples.
- **PII Storage Sanitization Gate (`PiiStorageSanitizationTest`)**: Verification in `tests/Feature/Ai/PiiStorageSanitizationTest.php` asserting that all prompts, responses, errors, and metadata in `ai_runs` are strictly redacted, ensuring zero plaintext Malaysian NRICs, credit cards, or secret tokens are ever stored in the database ([`ADR-015`](adr/ADR-015-quality-gates-e2e-testing-and-ai-observability-resilience.md)).
- **Registration Whitelist & Sovereign Isolation (`RegistrationWhitelistTest`)**: 100% test verification in `tests/Feature/Auth/RegistrationWhitelistTest.php` asserting non-whitelisted emails are rejected with 403 Forbidden, and whitelisted registered accounts receive fully isolated sovereign workspaces ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)).
- **Personal Privacy Policy Isolation (`PersonalNotePolicy`, `PersonalTaskPolicy`, `ChatSessionPolicy`)**: 100% test verification in `tests/Feature/Notes/PersonalNotePolicyTest.php`, `tests/Feature/Tasks/PersonalTaskPolicyTest.php`, and `tests/Feature/Chat/ChatSessionPolicyTest.php` ensuring records strictly enforce `auth()->id()` user scoping.
- **Fail-Closed Write Confirmation (`WriteSafetyProposalTest`)**: Verification in `tests/Feature/Mcp/WriteSafetyProposalTest.php` asserting that all write-mutation tools (`OutlookCreateDraftTool`, `OutlookReplyTool`, `OutlookForwardTool`) reject dispatch if an approval token is missing, forged, or expired. *(Note: Ticket reassignment gate is deferred per [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md))*

---

## Gate 3: Hermetic Test Suite, AI Resilience & Performance Bounds
- **Hermetic SQLite Sandbox**: 100% passing tests under in-memory SQLite (`./vendor/bin/pest --coverage-clover coverage.xml --parallel`).
- **Incremental Diff-Cover Gate**: Minimum **90% branch coverage** on modified lines in all PRs (`uvx diff-cover coverage.xml --compare-branch origin/main --fail-under=90`).
- **Multi-Provider AI Resilience (`AgentServiceResilienceTest`)**: 100% test verification asserting that primary provider rate limits automatically trigger fallback to secondary models (Anthropic $\to$ Gemini), and complete provider failures degrade gracefully into user-friendly notices without throwing HTTP 500 errors.
- **Database N+1 Query & Eloquent Hygiene Gate (`FilamentResourcesTest`)**:
  - `DatabaseQueryCounter::assertMaxQueries()` ensures all resource listings execute with $O(1)$ constant query bounds.
  - `Model::preventLazyLoading` and `Model::preventSilentlyDiscardingAttributes` active in `AppServiceProvider` to fail immediately on un-eager loaded relations or un-fillable attributes in testing/local environments.
- **Mutation Testing Gate (Pest `--mutate`)**: Enforced via `composer test:mutate` (`./vendor/bin/pest --mutate --covered-only`) ensuring test assertions actually catch logic and condition alterations.
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

## Gate 4: Playwright E2E, Visual & Accessibility QA (Beelal Coffee Pattern)
- **Headless Playwright Chromium & Mobile Runner**: Configured via `playwright.config.ts` running against the local PHP application:
  - **Auth & Session Journey (`01-auth-journey.spec.ts`)**: Asserts login form, CSRF token validation, and unauthorized redirect flows.
  - **Project Register Responsive Toolbar (`02-project-register-journey.spec.ts`)**: Asserts table column rendering, search inputs, and responsive toolbar action layout (zero horizontal viewport scroll on mobile 375px displays).
  - **Livewire Copilot Drawer & Action Cards (`03-copilot-drawer-journey.spec.ts`)**: Asserts topbar trigger render hook, Livewire drawer DOM hydration, and Action Card proposal modals.
- **Automated Accessibility Audit (`04-visual-and-accessibility.spec.ts` & `lens-for-laravel`)**:
  - `@axe-core/playwright` runs automated WCAG 2.1 Level AA conformance scans across core views.
  - `webcrafts-studio/lens-for-laravel` provides local-first accessibility audits via Browsershot (`php artisan lens:audit --wcag=2.1 --aa`).
- **Visual Regression Baselines**: Captures full-page viewport screenshots (`page.screenshot({ fullPage: true })`) to detect un-intended layout shifts or styling regressions.

---

## Gate 5: Safe Deployment Gating (`workflow_run`)
- **Zero Race Conditions**: Production deployment is **never** triggered directly by raw branch push.
- **Workflow Run Trigger**: Deploys execute strictly via `.github/workflows/deploy.yml` on `on: workflow_run: workflows: ["Quality Gate CI"]: types: [completed]` and assert `github.event.workflow_run.conclusion == 'success'` on the `main` branch.
