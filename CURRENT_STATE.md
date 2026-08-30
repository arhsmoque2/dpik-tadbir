# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: Livewire AI Copilot Drawer, Quality Gates & Observability Subsystem Complete ✅
- **Quality Gates Status**:
  - **Gate 1 (Static Analysis & Hygiene)**:
    - Laravel Pint: `passed` (0 style violations)
    - Larastan / PHPStan (Level 8): `[OK] No errors` across 103 files
    - FilaCheck (Filament v4 AST rules): `17/17 rules passed` across all resources
    - markdownlint: `0 issues in 27 files`
    - cspell: `0 issues in 29 files`
    - composer-unused: `0 unused, 0 zombies`
  - **Gate 2 & 3 (Security, Telemetry & Hermetic Tests)**:
    - 49 Hermetic Pest Tests `passed` (272 assertions) across Livewire Copilot Drawer, Outlook MCP Bridge, Email Interceptor, AI Run Observability, PII Detector, Cost Calculator, Auth, Notes, Tasks, Chat, MCP, Memory, and AI Agent services
- **Governing ADRs (ADR-001 through ADR-013)**:
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

## Active Invariants & Boundaries
1. **Email Whitelist Registration Gate**: Account registration is strictly restricted to pre-approved corporate emails (`allowed_registration_emails`), preventing unauthorized public signups ([`ADR-013`](docs/adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)).
2. **Sovereign Workspace Isolation**: Every whitelisted executive receives their own private Outlook mailbox credentials, chat sessions, personal notes, tasks, and presets with zero inter-user data leakage.
3. **Shared Enterprise Project Register**: Processed summaries and extracted commitments compound into a shared, company-wide SQLite FTS5 index with author attribution.
4. **Zero Raw Email Storage**: The app does not replicate Outlook or store raw emails; it queries Outlook on-demand via `outlook-mcp` (Graph API) and stores only processed outputs (summaries, commitments, notes, tasks).
5. **ARH Session Reader Memory Engine**: SQLite FTS5 full-text indexing + RRF fusion + decision markers (`dm:decision`, `dm:commitment`) across project registers and action receipts.
6. **Explicit Write Confirmation**: AI generates interactive Action Cards for drafting, replying, and forwarding; execution requires human approval with signed one-time tokens before Graph API dispatch.
7. **High-Density Memory Output**: Token-efficient pipe-delimited context formatting to inject decades of project memory into <500 tokens.
8. **Livewire AI Copilot Drawer**: Docks gracefully via Filament panel render hooks (`PanelsRenderHook::BODY_END` and `PanelsRenderHook::GLOBAL_SEARCH_AFTER`), providing keyboard-driven (`Cmd+J`) executive assistance, preset ribbon execution, and interactive HITL modals.

## Single Next Entry Point
- Conduct end-to-end sandbox walkthrough and deployment verification via Shawl / PM2 daemon service.
