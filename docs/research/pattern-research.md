# Pattern Research: Cross-Repository Quality Gates, E2E Testing & Observability Architecture

**Document ID**: `PR-003-QUALITY-OBSERVABILITY-PATTERNS`  
**Date**: 2026-08-30  
**Target Repository**: `dpik-tadbir`  
**Author**: Lead Architecture Agent  
**Governing Skill**: `arh-app-design-methodology`  

---

## 1. Executive Summary & Problem Space

Following the initial merge of PR #4 and PR #5 in **DPIK Tadbir**, a comprehensive quality gap analysis revealed 5 critical architectural blind spots across the system's runtime verification stack:
1. **Zero Visual & Accessibility (WCAG) Validation**: Livewire tests verified component PHP state machines but could not detect DOM layout breakage, table toolbar truncation on mobile viewports, or WCAG color contrast / keyboard navigation violations.
2. **Missing End-to-End (E2E) Multi-Step User Journeys**: Simulated HTTP tests did not validate real browser session handling, Microsoft OAuth redirects, or Livewire drawer opening and event hydration.
3. **Un-Faked AI Provider Outages & Failover**: `LlmGatewayService` lacked testing hooks for 429 rate limits and 503 timeouts, allowing upstream API outages to crash user requests with unhandled HTTP 500 errors.
4. **PII Storage Sanitization Vulnerability (PDPA Compliance)**: `PiiDetector` stripped outbound prompts, but raw matched PII was persisted into the `ai_runs` table metadata, risking persistent plaintext exposure of Malaysian NRICs and credit card numbers.
5. **Database N+1 Query Regressions & Silent Attr Discards**: Automated resource migrations risked introducing un-eager-loaded queries across Filament tables.

To solve these systemic blind spots with high leverage, this research evaluates proven architectural patterns across 4 internal repositories (`ARH-FNB-Beelal-Coffee`, `ARH-URUS`, `dpik-tugas-laravel`, `dpi-workops`) alongside external ecosystem packages (`webcrafts-studio/lens-for-laravel`, `visualbuilder/filament-screenshot-catalogue`, `didix16/laravel-playwright`, `@playwright/test`, `@axe-core/playwright`).

---

## 2. Inspected Repository Landscape

### A. `ARH-FNB-Beelal-Coffee` (`D:\ARH-GITHUB\arhsmoque2\ARH-FNB-Beelal-Coffee`)
* **Primary Pattern**: Headless Playwright UI & Multi-Viewport Layout Audit.
* **Key Architecture**:
  * Employs Chromium and Mobile Chrome device profiles to assert responsive boundary stability across desktop (1280px) and mobile (375px) displays.
  * Verifies the 8 core runtime states (`idle`, `loading`, `ready`, `active`, `success`, `empty`, `error`, `unavailable`) on active user surfaces.
  * Captures full-page viewport screenshots to establish an immutable visual regression baseline.
* **Applicability to DPIK Tadbir**: Directly adopted for Gate 4. Solves the table toolbar action overflow issue when Filament tables transition to smaller mobile screens.

### B. `ARH-URUS` (`D:\ARH-GITHUB\arhsmoque2\ARH-URUS`)
* **Primary Pattern**: AI Turn Observability, Multi-Provider Fallback, and Keyless CI/CD.
* **Key Architecture**:
  * Resilient LLM Gateway with primary provider (Anthropic) and automatic fallback (Gemini 2.5 Flash).
  * Anti-Hallucination Guard requiring signed action receipts for all mutating tool calls.
  * Keyless GCP Workload Identity Federation (WIF) preventing permanent service account JSON credential storage in GitHub Secrets.
  * `workflow_run` gated deployment preventing race conditions between test suites and Cloud Run deployments.
* **Applicability to DPIK Tadbir**: Informs the multi-provider failover design in `LlmGatewayService` and the graceful degradation pattern in `AgentService`.

### C. `DPIK Tugas Laravel` (`D:\ARH-GITHUB\arhsmoque2\dpik-tugas-laravel`)
* **Primary Pattern**: Filament v4 Domain Scaffolding, Strict Policy Scoping & SQLite In-Memory Hermetic Testing.
* **Key Architecture**:
  * Strict user-scoping policies (`PersonalNotePolicy`, `PersonalTaskPolicy`) enforcing multi-tenant executive isolation.
  * Dual-profile database architecture (SQLite `:memory:` for sub-second parallel test execution, managed Neon Postgres for production).
* **Applicability to DPIK Tadbir**: Informs the policy authorization gates and in-memory test isolation.

### D. `DPI WorkOps` (`D:\ARH-GITHUB\arhsmoque2\dpi-workops`)
* **Primary Pattern**: Multi-Linter Static Analysis & Preflight Secret Scanners.
* **Key Architecture**: Gitleaks pre-commit/pre-push scanning and automated JSON schema validation.
* **Applicability to DPIK Tadbir**: Integrated into Gate 1 and Gate 2.

---

## 3. Ecosystem Package Evaluation & Compatibility Matrix

Before adopting packages suggested during code review, each candidate was evaluated against our **Verify > Infer** and **Sustainable Leverage** directives:

| Candidate Package | Claimed Capability | Compatibility Audit Result | Verdict |
| :--- | :--- | :--- | :--- |
| **`visualbuilder/filament-screenshot-catalogue`** | Filament panel screenshot capture & catalogue | Requires `filament/filament ^5.0`. Fails dependency resolution on current Filament v4 / Laravel 12 setup. | **Rejected** (Version Conflict) |
| **`webcrafts-studio/lens-for-laravel`** | Local-first WCAG accessibility auditor via Browsershot & axe-core | Compatible with Laravel 12 (illuminate/support v10 to v13) on PHP 8.4. Provides `php artisan lens:audit`. | **Adopted** (v3.2) |
| **`didix16/laravel-playwright`** | Laravel Playwright boilerplate | Restricted to illuminate/console v8 to v11. Hard collision with Laravel 12. | **Rejected** (Outdated Constraints) |
| **`@playwright/test` + `@axe-core/playwright`** | Canonical browser E2E, visual snapshots, and WCAG AA audits | Node.js native (`package.json`), independent of PHP package version churn. Supported in GHA runners. | **Adopted** (v1.50+ / v4.10+) |
| **`laraveldaily/filacheck`** | Filament v4 AST linter & deprecation detector | Native Filament v4 support. Identifies deprecated v3 calls (`bulkActions`) and enforces 17 AST rules. | **Adopted** (v1.2) |
| **`kirschbaum-development/mail-intercept`** | In-process outbound mail interception | Clean Laravel 12 compatibility; intercepts SMTP emails without external mail trap daemons. | **Adopted** (v1.1) |

---

## 4. Adopted Patterns & Architectural Receipts

### Pattern 1: Native Playwright E2E & WCAG Accessibility Gate (Gate 4)
* **Design**: Rather than forcing outdated Laravel-Playwright wrapper packages, Playwright runs natively via `@playwright/test` against the local PHP server (`http://127.0.0.1:8000`).
* **Test Suites Created**:
  1. `tests/Browser/01-auth-journey.spec.ts`: Login page rendering, CSRF validation, unauthenticated redirect handling.
  2. `tests/Browser/02-project-register-journey.spec.ts`: Data table rendering, search inputs, responsive layout checks (asserts zero horizontal overflow on mobile viewports).
  3. `tests/Browser/03-copilot-drawer-journey.spec.ts`: Topbar trigger render hook, Livewire Copilot drawer DOM structure, Action Card modal display.
  4. `tests/Browser/04-visual-and-accessibility.spec.ts`: Automated WCAG 2.1 Level AA conformance scan (`@axe-core/playwright`) and full-page viewport screenshot snapshots.

### Pattern 2: Multi-Provider AI Resilience & Graceful Failover
* **Design**: `LlmGatewayService` implements a stateful provider switch. When primary provider (`anthropic`) encounters a 429 rate limit or network timeout:
  1. A warning signal is logged.
  2. The gateway switches `activeProvider` to `gemini` (`gemini-2.5-flash`).
  3. The completion is fulfilled transparently, and `AiRun` records `provider: 'gemini'`.
* **Graceful Degradation**: When all upstream providers are unreachable, `AgentService` wraps the turn in a `try-catch`, invokes `AiRunRecorder::recordFailure()`, logs `status: 'failed'` with the redacted error message, and returns a friendly user notice instead of an HTTP 500 server crash.

### Pattern 3: Strict PII Storage Sanitization (PDPA Invariant)
* **Design**: Under Malaysian PDPA and enterprise security requirements, plaintext NRIC numbers and credit cards must never be stored in application logs or telemetry tables.
* **Implementation**:
  * `PiiDetector` provides `detectCounts()` and `redactArray()` to safely process structured JSON without retaining plaintext matches.
  * `AiRunRecorder::record()` calls `PiiDetector::redact()` on `payload`, `response`, `error_message`, and all nested `metadata` keys prior to `AiRun::create()`.
  * `metadata['pii_types']` stores only the type identifiers (e.g. `['nric_formatted']`) and counts, strictly omitting raw sensitive strings.

### Pattern 4: Eloquent Performance Bounds & Mutation Testing
* **Design**: Prevent silent N+1 query regressions and mass-assignment discarding:
  * `Model::preventLazyLoading(! app()->isProduction())` and `Model::preventSilentlyDiscardingAttributes(! app()->isProduction())` enabled in `AppServiceProvider`.
  * Created `DatabaseQueryCounter` trait with `assertMaxQueries(int $max, callable $callback)` to assert $O(1)$ query complexity on Filament resource table rendering.
  * Integrated Pest mutation testing (`vendor/bin/pest --mutate --covered-only`) into `composer.json` and CI Gate 3.

---

## 5. Traceability to Governing Documents

* **Intent & Scenarios**: Traces to `docs/INTENT.md` and `docs/SCENARIOS.md` (Executive Assistant reliability).
* **Quality Gates**: Formalized in `docs/QUALITY-GATES.md`.
* **Architectural Decisions**: Accepted in `docs/adr/ADR-015-quality-gates-e2e-testing-and-ai-observability-resilience.md`.
