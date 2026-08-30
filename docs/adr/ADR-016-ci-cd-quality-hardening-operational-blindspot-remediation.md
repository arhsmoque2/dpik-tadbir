# ADR-016: Operational Blind Spot Remediation, Playwright Authentication Seeding, CI Gate Decoupling, and PII Error Sanitization

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

---

## Context

Following the implementation of ADR-014 (Devtools & Cascade) and ADR-015 (E2E Testing & AI Observability), a forensic review of PR #5 (`feature/quality-tooling-and-ci-upgrades`) and cross-project analysis against `dpik-tugas-laravel` identified critical operational and architectural execution blind spots:

1. **Playwright E2E Structural Auth Bypass**: While `@playwright/test` was installed and spec files authored, CI executed `php artisan migrate --force` against an unseeded database. When `02-project-register-journey.spec.ts` and `03-copilot-drawer-journey.spec.ts` loaded `/admin/project-registers` and `/admin`, Filament issued a 302 redirect to `/admin/login`. Both specs contained guard blocks (`if (page.url().includes('/admin/login')) return;`) that exited immediately, making the table, responsive toolbar, and copilot drawer assertions into effective no-ops in CI.
2. **The Testing Web Server State Trap**: Attempting to boot `php artisan serve --env=testing` without an explicit file-backed database configuration causes Laravel to inherit `phpunit.xml` settings (`DB_DATABASE=:memory:` and `SESSION_DRIVER=array`). Under `php artisan serve`, each incoming HTTP request runs in an isolated worker process; an in-memory SQLite database and array session driver destroy authentication cookies and data across consecutive requests.
3. **Toothless & Timing-Out Mutation Testing**: Mutation testing was wired into CI Gate 3 as `./vendor/bin/pest --mutate --covered-only --min=80 || true`. The `|| true` suppressed failures, creating an honesty gap against `QUALITY-GATES.md`. Furthermore, forensic inspection of Pest 3.7 revealed that `--dirty` is not a supported flag for `--mutate`, and GitHub Actions runners operate with clean checkouts (zero dirty files). Running full mutation testing on PRs adds 15–45 minutes of runner overhead.
4. **Latent PII Leak in Error Metadata**: While `AiRunRecorder` sanitized `$errorMessage` before writing to the `ai_runs` table, `AgentService.php` wrote raw `$e->getMessage()` directly into `chat_messages.metadata['error']` and `Log::error()`. An upstream exception echoing user input (e.g. invalid Malaysian NRIC or credit card) would leak unredacted PII into conversation history.
5. **AI Telemetry Provider Attribution**: `AgentService` relied on ambient mutable state via `$this->llmGateway->getActiveProvider()` rather than receiving explicit provider attribution in the turn completion payload.
6. **Deployment Configuration Gaps**: `deploy.yml` omitted `ALLOWED_REGISTRATION_EMAILS` and `APP_URL`, which would cause authorization failures on live Cloud Run revisions.

---

## Decision

### 1. Deterministic Seeding & Playwright Global StorageState
- **Seeder Implementation**: Author `database/seeders/DatabaseSeeder.php` using fixed, deterministic fixtures (no randomized Faker calls). Fixtures include:
  - User: `admin@dpik.com.my`, role `super_admin`, password `password`.
  - Whitelist: `admin@dpik.com.my` in `allowed_registration_emails`.
  - Project Register: `PC-2023-011` ("Jambatan Sungai Udang"), ensuring table data renders in E2E tests.
  - Executive Preset: "Tender Review Brief", ensuring the copilot presets ribbon hydrates.
  - Guard against production execution with `if (app()->environment('production')) return;`.
- **Global Auth Setup**: Implement `tests/Browser/auth.setup.ts` using Playwright's project dependency architecture. The setup logs into `/admin/login`, asserts navigation to `/admin`, and writes auth cookies to `playwright/.auth/user.json`.
- **E2E Assertion Hardening**:
  - Remove all early-return guards (`if (/admin/login) return;`) from Journeys 02 and 03.
  - Add explicit data attributes: `data-copilot-trigger` in `resources/views/filament/hooks/copilot-topbar-button.blade.php` and `data-copilot-drawer` in `resources/views/livewire/ai-copilot-drawer.blade.php`.
  - In `04-visual-and-accessibility.spec.ts`, replace the dummy byte-length check with native `expect(page).toHaveScreenshot('admin-login.png', { maxDiffPixelRatio: 0.05, animations: 'disabled' })` and re-enable `color-contrast`.

### 2. Persistent Testing Environment for Web Server
- In CI Gate 4 and local testing runs, prepare a dedicated `.env.testing` pointing to a file-backed SQLite database:
  - `DB_DATABASE=database/testing.sqlite`
  - `SESSION_DRIVER=database`
  - `CACHE_STORE=database`
- Configure `playwright.config.ts`'s `webServer` to launch `php artisan serve --env=testing --port=8000`, ensuring session cookies and database rows persist across requests.

### 3. Decouple Mutation Testing & Split Security Preflight
- **PR Pipeline (Gate 3)**: Rely on the **90% incremental diff-cover gate** (`uvx diff-cover coverage.xml --compare-branch origin/main --fail-under 90`). Remove `pest --mutate` from the PR path to preserve rapid developer feedback loops (<3 minutes).
- **Scheduled Weekly Audit**: Establish `.github/workflows/weekly-quality-audit.yml` running on `cron: '0 2 * * 0'` and `workflow_dispatch`. Execute full mutation testing (`./vendor/bin/pest --mutate --covered-only --parallel --min=70`) without `|| true`, alongside Spatie/Browsershot `lens:audit`.
- **Gate 2 Security Split**: Promote the 4 security/privacy test suites (`RegistrationWhitelistTest`, `PolicyAuthorizationTest`, `WriteSafetyProposalTest`, `PiiStorageSanitizationTest`) to run directly inside the Gate 2 CI job alongside Gitleaks, creating a true fail-closed security preflight.

### 4. Comprehensive PII Error Sanitization & Explicit Provider DTO
- Inject `PiiDetector` into `AgentService`.
- Sanitize `$e->getMessage()` via `$this->piiDetector->redact()` before passing to `ChatMessage::create()` metadata and `Log::error()`.
- Update `LlmGatewayService::complete()` to return `provider` and `model` in its completion payload, passing `$completion['provider']` explicitly to `AiRunRecorder::record()`.
- Add test cases in `PiiStorageSanitizationTest.php` asserting that raw NRICs and credit card numbers in simulated exception messages are redacted in both `ai_runs` and `chat_messages`.

### 5. Deployment Secrets & Ported Checklists
- Update `.github/workflows/deploy.yml` with `ALLOWED_REGISTRATION_EMAILS: ${{ vars.ALLOWED_REGISTRATION_EMAILS }}` and `APP_URL: ${{ vars.APP_URL }}`.
- Create `docs/deployment-readiness-checklist.md` documenting Cloud Run revision sequencing and the Neon unpooled direct connection requirement for `php artisan migrate`.

---

## Consequences

- **Positive**: Playwright E2E tests execute genuine user journeys against fully hydrated Filament tables and Livewire copilot drawers.
- **Positive**: Zero PII leakage across database columns, JSON metadata, or application log files during AI provider outages.
- **Positive**: Accurate cost calculation and model telemetry with explicit provider attribution during failover events.
- **Positive**: PR CI runs in under 3 minutes with blocking diff-cover quality gates, eliminating toothless `|| true` suppressions.
- **Positive**: Cloud Run deployments receive complete environment configurations matching ADR-013 sovereign isolation rules.
