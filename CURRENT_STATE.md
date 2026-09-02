# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: All 6 Phases Complete & Hardened — AI Control Plane, Session Export, Native IMAP/SMTP Probes, and Tadbir Runtime Control Plane Verified ✅
- **Quality Gates Status**:
  - **Gate 1 (Static Analysis & Hygiene)**:
    - Laravel Pint: `passed` (0 style violations)
    - Larastan / PHPStan (Level 8, per `phpstan.neon`): `[OK] No errors` with `--memory-limit=1G`
    - FilaCheck (Filament v4 AST rules): `18/18 rules passed` across all resources
    - markdownlint: `0 issues in 42 files`
    - cspell: `0 issues in 42 files`
    - composer-unused: `0 unused, 0 zombies`
  - **Gate 2 & 3 (Security Preflight, Telemetry & Hermetic Tests)**:
    - Gate 2 standalone security preflight job (Gitleaks + Whitelist, Policy, Write-Safety, and PII storage tests)
    - **187 Hermetic Pest Tests** `passed` (860 assertions) including user-configurable API key encryption, executive whitelist zero-gating, OpenRouter completions & error passthrough, error message PII sanitization in both `ai_runs` and `chat_messages`, `Bundle` model persistence & relationships, `BundleResource` Filament index rendering, `BundleService` auto-naming, `AutoPromotionService` 7-day rolling query, `CopilotBundleScopingTest` in-chat prompt injection, `UserBottomNavSlotsTest` custom preferences, episodic session exports (JSONL / SQLite FTS5), and live socket interception for IMAP/SMTP health diagnostics.
    - 90% diff-cover gate on PRs (`uvx diff-cover`), with exhaustive mutation testing (`pest --mutate`) decoupled to weekly scheduled audits
    - Strict Eloquent hygiene: `Model::preventLazyLoading` and `Model::preventSilentlyDiscardingAttributes` active
  - **Gate 4 (E2E, Visual & Accessibility QA)**:
    - Playwright suite in `tests/Browser` with global auth storageState (`auth.setup.ts`) covering core user journeys (Auth & SSO, Project Register responsive toolbar actions, AI Copilot drawer, Action Cards, and Mail Bundles)
    - WCAG 2.1 Level AA conformance checks via `@axe-core/playwright` without disabling color contrast, backed by `DatabaseSeeder` deterministic fixtures
    - Full-Page viewport screenshot snapshots with `maxDiffPixelRatio: 0.05` for visual regression detection
  - **Runtime Control Plane (`tadbir`)**:
    - Zero-runtime-dependency Python CLI (`tools/tadbir.py` + `tools/tadbir_cli/`); `gate`/`test-triage` parse tool output into JSON metrics; `status` scopes CI to the current branch.
    - 6 project-local snip filters (`.snip/filters/*.yaml`, 14 inline `snip verify` tests) compress raw `pest`/`phpstan`/`pint`/`gh run`/`php artisan migrate` output run through snip's PreToolUse hook. Register per machine with `python tools/tadbir.py snip-setup`.
    - `gate` is the fast subset; `composer check:full` stays the authoritative pre-merge gate.
    - Emits raw empirical state (test counts, assertions, exit codes) with no human verdict artifacts.

- **Governing ADRs (ADR-001 through ADR-030)**:
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
  - `docs/adr/ADR-024-google-oauth-superadmin-auto-provisioning.md` (Google OAuth SuperAdmin Auto-Provisioning)
  - `docs/adr/ADR-025-episodic-session-export-and-cross-agent-fts5-interoperability.md` (Episodic Session Export & Cross-Agent FTS5 Interoperability)
  - `docs/adr/ADR-026-live-ai-and-mcp-control-plane-json-architecture.md` (Live AI & MCP Control Plane JSON Architecture)
  - `docs/adr/ADR-027-universal-mail-transport-and-health-diagnostics.md` (Universal Mail Transport & Health Diagnostics)
  - `docs/adr/ADR-028-closed-loop-skill-synthesis-and-composed-actions.md` (Closed-Loop Skill Synthesis & Composed Actions)
  - `docs/adr/ADR-029-hermetic-io-stream-and-socket-mocking-architecture.md` (Hermetic I/O Stream & Socket Mocking Architecture)
  - `docs/adr/ADR-030-tadbir-runtime-control-plane-snip-output-filtering-and-state-doctrine.md` (Tadbir Runtime Control Plane, Snip Output Filtering & State-First Doctrine)
  - `docs/adr/ADR-031-cloud-run-cold-start-and-performance-optimization.md` (Cloud Run Cold-Start Mitigation, Caddy Static Asset Caching, Filament SPA Navigation, and Sovereign Settings Persistence)

## Implementation Boundaries & Deferred Refinements
- **Dashboard Design Contract (ADR-022 / CAP-009)**: The default dashboard is strictly a calm Bundle & AI Session list. Stat-card metric widgets (`ExecutiveStatsOverview`) remain decoupled from the default view per ADR-022's human-first, AI-optional directive.
- **Hermes Closed-Loop Skill Synthesis (ADR-028)**: Accepted architectural specification for future Phase 2 development. Composed domain tools (`prepare_email_reply_draft`, `tender_auto_intake`) and proactive skill promotion prompts are intentionally reserved and not yet implemented in code.
- **Adaptive Mobile Navigation (CAP-020)**: 4-slot bottom nav customization with persistent Copilot anchor and user-customizable slot arrangement is fully active. Advanced compound controls (Home-as-expandable-menu, Retrieve filter chips, More drawer) are documented design targets reserved for future mobile polish passes.

## Active Invariants & Boundaries
1. **Email Whitelist & Sovereign Executive Roles**: Account registration is strictly restricted to pre-approved corporate emails (`allowed_registration_emails`), preventing unauthorized public signups ([`ADR-013`](docs/adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)). `rahman@dpik.com.my`, `smoque@gmail.com`, and `arh.homelab@gmail.com` are permanent un-gated Super Admins; `hilmio@dpik.com.my` (Managing Director) and `hamid@dpik.com.my` (Corporate Administrator) are standard executive users.
2. **User-Configurable AI Provider Keys & OpenRouter Catalog**: Each executive can configure their private Anthropic (Claude 3.7), Gemini, and OpenRouter API keys via `ExecutiveSettings`, stored encrypted at rest. When unset, Tadbir gracefully falls back to system / SOPS credentials. In-chat 3-favorites swapper (`Cmd+J`) enables zero-reload model switching between executive favorites ([`ADR-018`](docs/adr/ADR-018-openrouter-multi-model-catalog-and-runtime-favorites-swapper.md)).
3. **Materialized Bundles & Metadata-Only Persistence**: Email retrievals create a materialized `Bundle` with lightweight email pointers (`message_id`, `from_name`, `from_email`, `subject`, `snippet`, `received_at`). Zero raw email body text or HTML attachments are persisted on local disk ([`ADR-022`](docs/adr/ADR-022-bundle-based-retrieval-ai-optional-review-and-adaptive-navigation.md), [`ADR-023`](docs/adr/ADR-023-metadata-only-bundle-persistence.md)).
4. **Deterministic Control Plane (`tadbir`)**: Every agent session begins with `python tools/tadbir.py status` and runs `python tools/tadbir.py gate` before pushing. All outputs emit pure machine state metrics ([`ADR-030`](docs/adr/ADR-030-tadbir-runtime-control-plane-snip-output-filtering-and-state-doctrine.md)).
5. **Cloud Sandbox Protocol**: In Claude Code web, Codespaces, or headless Linux Docker containers, preflights run hermetically via `bash scripts/sandbox-preflight.sh` or `python3 tools/tadbir.py gate` with zero external dependency setup.

## Single Next Entry Point
- Push current branch to remote and verify Quality Gate CI run.
