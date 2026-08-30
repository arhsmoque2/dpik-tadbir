# ADR-015: End-to-End Browser Testing, WCAG Accessibility, AI Failover Resilience, and PII Storage Sanitization

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

---

## Context

Following the review of PR #5 (`feature/quality-tooling-and-ci-upgrades`), a gap analysis revealed 5 critical blind spots in the system's testing and runtime resilience:

1. **Visual & Accessibility QA**: The test suite only exercised Livewire component state without validating actual browser rendering, WCAG 2.1 Level AA color contrast, or table toolbar layout shifts on small viewports.
2. **End-to-End (E2E) Browser Journeys**: Livewire tests operate in a simulated HTTP environment and cannot test real JavaScript events, Microsoft OAuth redirects, or Livewire drawer hydration.
3. **AI API Error Resilience & Faking**: `AgentService` lacked error-handling branches for upstream provider rate limits (HTTP 429) or timeouts, leading to unhandled HTTP 500 errors.
4. **PII Storage Sanitization (PDPA)**: `PiiDetector` stripped outbound prompts, but raw matched PII strings were persisted in the `ai_runs` database metadata, creating a major privacy risk.
5. **Database N+1 Query Regressions**: No safeguards prevented accidental lazy-loading in newly auto-migrated Filament resources.

---

## Decision

### 1. Adopt Native Playwright Runner & Axe-Core for Gate 4
- Install `@playwright/test` and `@axe-core/playwright` as the canonical E2E testing framework.
- Scaffold 4 core test suites in `tests/Browser/`:
  - `01-auth-journey.spec.ts`: Login, CSRF verification, and redirection handling.
  - `02-project-register-journey.spec.ts`: Table columns, search input, and responsive mobile toolbar layout.
  - `03-copilot-drawer-journey.spec.ts`: Topbar trigger render hook, Livewire drawer DOM structure, and Action Card presentation.
  - `04-visual-and-accessibility.spec.ts`: WCAG 2.1 Level AA audit and full-page viewport screenshot snapshots.
- Install `webcrafts-studio/lens-for-laravel` (v3.2) to provide local artisan-driven accessibility audits (`php artisan lens:audit`).
- *Rationale*: Avoids broken legacy wrappers (`didix16/laravel-playwright` restricted to Laravel $\le 11$; `filament-screenshot-catalogue` requiring Filament v5).

### 2. Implement Multi-Provider AI Fallback & Graceful Degradation
- In `LlmGatewayService`:
  - Add `LlmGatewayService::fake()` and `fakeSequence()` for deterministic failure simulation in tests.
  - If the primary provider (`anthropic`) throws an exception or hits a rate limit, automatically trigger fallback to `gemini` (`gemini-2.5-flash`), update `activeProvider`, and proceed transparently.
- In `AgentService`:
  - Wrap turns in a resilient `try-catch`.
  - When all providers fail, invoke `AiRunRecorder::recordFailure()` to record `status: 'failed'` and the error message, while returning a friendly, non-crashing response to the user.

### 3. Enforce Strict PII Storage Sanitization Before Persistence
- Under Malaysian PDPA guidelines, no plaintext NRIC or credit card numbers may be persisted.
- Add `payload`, `response`, and `error_message` columns to `ai_runs`.
- Enhance `PiiDetector` with `detectCounts()` and `redactArray()`.
- In `AiRunRecorder::record()`, run `PiiDetector::redact()` on all fields before `AiRun::create()`.
- Store only detected PII type names and counts in `metadata['pii_types']`, strictly omitting raw matched values.

### 4. Enforce Eloquent Query Bounds & Mutation Testing
- Enable `Model::preventLazyLoading(! app()->isProduction())` and `Model::preventSilentlyDiscardingAttributes(! app()->isProduction())` in `AppServiceProvider::boot()`.
- Create `DatabaseQueryCounter` trait with `assertMaxQueries(int $max, callable $callback)` to assert $O(1)$ query bounds on Filament table views.
- Add `"test:mutate": "vendor/bin/pest --mutate --covered-only"` to `composer.json` and CI Gate 3.

---

## Consequences

- **Positive**: Complete coverage for multi-step browser flows, Microsoft OAuth redirects, and Livewire drawer interactions.
- **Positive**: Zero plaintext PII stored in telemetry tables or application metadata.
- **Positive**: Zero HTTP 500 errors during upstream AI rate limits or network outages.
- **Positive**: Strict compile-time and runtime protection against N+1 query regressions.
- **Trade-off**: CI Gate 4 requires Chromium browser installation on GitHub Actions runners (~30s added runner time).
