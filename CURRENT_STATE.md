# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: Quality Hardening Complete — E2E User Journeys, Visual & WCAG QA, AI Resilience & PII Storage Sanitization Verified ✅
- **Quality Gates Status**:
  - **Gate 1 (Static Analysis & Hygiene)**:
    - Laravel Pint: `passed` (0 style violations)
    - Larastan / PHPStan (Level 8): `[OK] No errors` across 104 files
    - FilaCheck (Filament v4 AST rules): `17/17 rules passed` across all resources
    - markdownlint: `0 issues in 28 files`
    - cspell: `0 issues in 30 files`
    - composer-unused: `0 unused, 0 zombies`
  - **Gate 2 & 3 (Security Preflight, Telemetry & Hermetic Tests)**:
    - Gate 2 standalone security preflight job (Gitleaks + Whitelist, Policy, Write-Safety, and PII storage tests)
    - 62 Hermetic Pest Tests `passed` (363 assertions) including user-configurable API key encryption, executive whitelist zero-gating, and error message PII sanitization in both `ai_runs` and `chat_messages`
    - 90% diff-cover gate on PRs (`uvx diff-cover`), with exhaustive mutation testing (`pest --mutate`) decoupled to weekly scheduled audits
    - Strict Eloquent hygiene: `Model::preventLazyLoading` and `Model::preventSilentlyDiscardingAttributes` active
  - **Gate 4 (E2E, Visual & Accessibility QA)**:
    - Playwright suite in `tests/Browser` with global auth storageState (`auth.setup.ts`) covering 3 core user journeys (Auth & SSO, Project Register responsive toolbar actions, AI Copilot drawer & Action Cards)
    - WCAG 2.1 Level AA conformance checks via `@axe-core/playwright` without disabling color contrast, backed by `DatabaseSeeder` deterministic fixtures
    - Full-page viewport screenshot snapshots with `maxDiffPixelRatio: 0.05` for visual regression detection
- **Governing ADRs (ADR-001 through ADR-016)**:
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

## Active Invariants & Boundaries
1. **Email Whitelist & Sovereign Executive Roles**: Account registration is strictly restricted to pre-approved corporate emails (`allowed_registration_emails`), preventing unauthorized public signups ([`ADR-013`](docs/adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)). `rahman@dpik.com.my`, `smoque@gmail.com`, and `arh.homelab@gmail.com` are permanent un-gated Super Admins; `hilmio@dpik.com.my` (Managing Director) and `hamid@dpik.com.my` (Corporate Administrator) are standard executive users.
2. **User-Configurable AI Provider Keys**: Each executive can configure their private Anthropic (Claude 3.7) and Gemini API keys via `ExecutiveSettings`, stored encrypted at rest. When unset, Tadbir gracefully falls back to system / SOPS credentials.
3. **Sovereign Workspace Isolation**: Every whitelisted executive receives their own private Outlook mailbox credentials, chat sessions, personal notes, tasks, and presets with zero inter-user data leakage.
4. **Shared Enterprise Project Register**: Processed summaries and extracted commitments compound into a shared, company-wide SQLite FTS5 index with author attribution.
5. **Zero Raw Email Storage**: The app does not replicate Outlook or store raw emails; it queries Outlook on-demand via `outlook-mcp` (Graph API) and stores only processed outputs (summaries, commitments, notes, tasks).
6. **ARH Session Reader Memory Engine**: SQLite FTS5 full-text indexing + RRF fusion + decision markers (`dm:decision`, `dm:commitment`) across project registers and action receipts.
7. **Explicit Write Confirmation**: AI generates interactive Action Cards for drafting, replying, and forwarding; execution requires human approval with signed one-time tokens before Graph API dispatch.
8. **High-Density Memory Output**: Token-efficient pipe-delimited context formatting to inject decades of project memory into <500 tokens.
9. **Livewire AI Copilot Drawer**: Docks gracefully via Filament panel render hooks (`PanelsRenderHook::BODY_END` and `PanelsRenderHook::GLOBAL_SEARCH_AFTER`), providing keyboard-driven (`Cmd+J`) executive assistance, preset ribbon execution, and interactive HITL modals.
10. **Provisioned Cloud Run & Neon Infrastructure**: Neon Serverless project `floral-haze-01285681` (`DPIK-Tadbir`), GCP Artifact Registry `dpik-tadbir`, and WIF bindings active.

## Single Next Entry Point
- Trigger Gate 5 CI deployment to Cloud Run or run local preview via Shawl / PM2 daemon service.
