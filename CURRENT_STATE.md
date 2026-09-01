# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: All 6 Phases Complete — Materialized Bundles, Copilot Scoping, Adaptive Nav & Live Graph API Body Fetch Verified ✅
- **Quality Gates Status**:
  - **Gate 1 (Static Analysis & Hygiene)**:
    - Laravel Pint: `passed` (0 style violations)
    - Larastan / PHPStan (Level 8): `[OK] No errors` across 112 files
    - FilaCheck (Filament v4 AST rules): `18/18 rules passed` across all resources
    - markdownlint: `0 issues in 40 files`
    - cspell: `0 issues in 40 files`
    - composer-unused: `0 unused, 0 zombies`
  - **Gate 2 & 3 (Security Preflight, Telemetry & Hermetic Tests)**:
    - Gate 2 standalone security preflight job (Gitleaks + Whitelist, Policy, Write-Safety, and PII storage tests)
    - 125 Hermetic Pest Tests `passed` (628 assertions) including user-configurable API key encryption, executive whitelist zero-gating, OpenRouter completions & error passthrough, error message PII sanitization in both `ai_runs` and `chat_messages`, `Bundle` model persistence & relationships, `BundleResource` Filament index rendering, `BundleService` auto-naming, `AutoPromotionService` 7-day rolling query, `CopilotBundleScopingTest` in-chat prompt injection, `UserBottomNavSlotsTest` custom preferences, and live Graph API body fetch action on `ViewBundle`.
    - 90% diff-cover gate on PRs (`uvx diff-cover`), with exhaustive mutation testing (`pest --mutate`) decoupled to weekly scheduled audits
    - Strict Eloquent hygiene: `Model::preventLazyLoading` and `Model::preventSilentlyDiscardingAttributes` active
  - **Gate 4 (E2E, Visual & Accessibility QA)**:
    - Playwright suite in `tests/Browser` with global auth storageState (`auth.setup.ts`) covering core user journeys (Auth & SSO, Project Register responsive toolbar actions, AI Copilot drawer, Action Cards, and Mail Bundles)
    - WCAG 2.1 Level AA conformance checks via `@axe-core/playwright` without disabling color contrast, backed by `DatabaseSeeder` deterministic fixtures
    - Full-Page viewport screenshot snapshots with `maxDiffPixelRatio: 0.05` for visual regression detection
- **Governing ADRs (ADR-001 through ADR-023)**:
  - `docs/adr/ADR-001-stack-selection.md` (Laravel 12 + Filament v4 + MCP; zero local raw email storage)
  - `docs/adr/ADR-002-ai-model-and-provider-governance.md` (Multi-Provider, Fallbacks, Prompts)
  - `docs/adr/ADR-003-outlook-mcp-email-processor-boundary.md` (Zero Raw Email Storage Boundary)
  - `docs/adr/ADR-004-executive-presets-and-quick-action-engine.md` (User-Scoped Presets & Dynamic Templating)
  - `docs/adr/ADR-005-project-register-and-continuous-memory.md` (Project Register & Domain Memory)
  - `docs/adr/ADR-006-hybrid-memory-search-and-retrieval-engine.md` (SQLite FTS5, RRF, Dense Context)
  - `docs/adr/ADR-007-write-safety-human-in-the-loop-approval-gates.md` (Action Cards, Approval Gates)
  - `docs/adr/ADR-008-action-receipts-and-automated-activity-rollups.md` (Action Ledger & Rollups)
  - `docs/adr/ADR-009-system-settings-and-runtime-configurability.md` (Zero-Hardcoding Settings Store)
  - `docs/adr/ADR-010-continual-executive-personalization-engine.md` (Behavioral Adaptation & Executive Persona Profile)
  - `docs/adr/ADR-011-interactive-ui-modals-and-human-in-the-loop-tools.md` (Interactive Modals, Choice Pickers & State Machine)
  - `docs/adr/ADR-012-scope-reduction-defer-project-staff-oversight.md` (Scope Reduction: Defer Project/Staff Oversight & Ticketing)
  - `docs/adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md` (Email Whitelist Registration Gate & Multi-Executive Sovereign Workspaces)
  - `docs/adr/ADR-014-agent-devtooling-quality-automation-and-auto-fix-cascade.md` (Agent Devtooling, Quality Automation & Auto-Fix Cascade)
  - `docs/adr/ADR-015-quality-gates-e2e-testing-and-ai-observability-resilience.md` (E2E Browser Testing, WCAG Accessibility, AI Failover Resilience & PII Storage Sanitization)
  - `docs/adr/ADR-016-ci-cd-quality-hardening-operational-blindspot-remediation.md` (Operational Blind Spot Remediation, Playwright Auth Seeding, CI Gate Decoupling & PII Error Sanitization)
  - `docs/adr/ADR-017-runtime-integrations-and-graceful-error-fallback-guards.md` (Runtime Integrations, Live Connection Reflection Probes & Graceful Fallback Guards)
  - `docs/adr/ADR-018-openrouter-multi-model-catalog-and-runtime-favorites-swapper.md` (OpenRouter Unified Multi-Model Gateway & In-Chat 3-Favorites Runtime Swapper)
  - `docs/adr/ADR-019-proportionate-ci-gating-and-zero-drift-docs-verification.md` (Proportionate CI Gating, Path-Based Change Classification & Zero-Drift Docs Verification)
  - `docs/adr/ADR-020-hermetic-sandbox-dependency-hydration-and-degraded-fallback-protocol.md` (Hermetic Sandbox Dependency Hydration, Pre-Compiled Release Bundles & Degraded Fallback Protocol)
  - `docs/adr/ADR-021-ai-cost-governance-prompt-caching-and-context-mode-budgets.md` (Anthropic Prompt Caching & Per-Turn Telemetry in `AiRun`)
  - `docs/adr/ADR-022-bundle-based-retrieval-ai-optional-review-and-adaptive-navigation.md` (Materialized Bundles, Human-First Review & Adaptive Navigation)
  - `docs/adr/ADR-023-metadata-only-bundle-persistence.md` (Metadata-Only Persistence Directive & Live Graph API Body Fetching)

## Active Invariants & Boundaries
1. **Email Whitelist & Sovereign Executive Roles**: Account registration is strictly restricted to pre-approved corporate emails (`allowed_registration_emails`), preventing unauthorized public signups ([`ADR-013`](docs/adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)). `rahman@dpik.com.my`, `smoque@gmail.com`, and `arh.homelab@gmail.com` are permanent un-gated Super Admins; `hilmio@dpik.com.my` (Managing Director) and `hamid@dpik.com.my` (Corporate Administrator) are standard executive users.
2. **User-Configurable AI Provider Keys & OpenRouter Catalog**: Each executive can configure their private Anthropic (Claude 3.7), Gemini, and OpenRouter API keys via `ExecutiveSettings`, stored encrypted at rest. When unset, Tadbir gracefully falls back to system / SOPS credentials. In-chat 3-favorites swapper (`Cmd+J`) enables zero-reload model switching between executive favorites ([`ADR-018`](docs/adr/ADR-018-openrouter-multi-model-catalog-and-runtime-favorites-swapper.md)).
3. **Materialized Bundles & Metadata-Only Persistence**: Email retrievals create a materialized `Bundle` with lightweight email pointers (`message_id`, `from_name`, `from_email`, `subject`, `snippet`, `received_at`). Zero raw email body text or HTML attachments are persisted on local disk ([`ADR-022`](docs/adr/ADR-022-bundle-based-retrieval-ai-optional-review-and-adaptive-navigation.md), [`ADR-023`](docs/adr/ADR-023-metadata-only-bundle-persistence.md)).
4. **Copilot Bundle Scoping**: Opening the AI Copilot drawer from a Bundle automatically binds `$session->bundle_id` and injects the materialized Bundle metadata + email pointers directly into the AI system prompt.
5. **Adaptive Mobile Bottom Navigation**: Executives can configure up to 4 custom bottom navigation slots stored in `$user->bottom_nav_slots`, defaulting to Copilot, Bundles, Notes, and Settings.
6. **Live Graph API Body Fetching**: Executives can inspect full email body contents live via `OutlookReadMessageTool` directly from `ViewBundle` with zero local disk storage.

## Single Next Entry Point
- Merge current feature branch into `main` and trigger Gate 5 CI deployment to Cloud Run.
