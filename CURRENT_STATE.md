# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: Design, Architecture, ADRs, Opportunities, CI/CD Quality Gates & Test Coverage Plan Complete $\rightarrow$ Ready for Codebase Scaffolding.
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
- **Progressive Lifecycle Specs**:
  - `docs/INTENT.md` ✅ Complete (Outlook MCP Processor, Zero Raw Storage, Whitelisted Registration, Sovereign Workspaces)
  - `docs/SCENARIOS.md` ✅ Complete (SCEN-01 to SCEN-04 & SCEN-07 active; SCEN-05 & SCEN-06 deferred per ADR-012)
  - `docs/CAPABILITIES.md` ✅ Complete (CAP-001 to CAP-017 reconciled; CAP-008/009 deferred per ADR-012)
  - `docs/LOCAL-CONTEXT.md` ✅ Complete (Malaysian Infrastructure & Consultancy Ecosystem, JKR/JPS/Air Selangor, BEM Scale of Fees)
  - `docs/OPPORTUNITIES.md` ✅ Complete (OPP-001 Tugas positioning & English internals, OPP-002 Workload visibility & HR sensitivity guardrails)
  - `docs/ARCHITECTURE.md` ✅ Complete (Outlook MCP Bridge, FTS5 + RRF, Token-Dense Context, Whitelist Guard, Sovereign User Storage)
  - `docs/DESIGN.md` ✅ Complete (Subsystems, Whitelist Service, User-Scoped Isolation Scopes, TicketPolicy contracts, Nominal Capacity definition, ExecutivePreset scoping, Action/Deliverable/PIC split)
  - `docs/UI.md` ✅ Complete (Desktop 3-Column Adaptive, TWA Mobile Thumb-Zone, Ink Tokens, Controlled Action Cards, 8 Runtime States)
  - `docs/research/PR-001-scouting-and-reusable-modules.md` ✅ Complete (URUS, Tugas, WorkOps Reusable Blueprint)
  - `docs/research/PR-002-ui-ux-design-patterns-and-layout-paradigms.md` ✅ Complete (Executive Command Center, Controlled Generative UI, TWA Mobile Ergonomics)
  - `docs/ui-spec/navigation-tree.json` ✅ Complete (Executive `super_admin` role, clean active navigation groups)
  - `docs/ui-spec/flow-diagram.mermaid` ✅ Complete
  - `docs/testing/coverage-risk-matrix.json` ✅ Complete (4-tier risk matrix aligned with active Phase 1 scope and ADR-012/ADR-013 updates)
  - `docs/testing/test-plan.md` ✅ Complete (Spec-to-Test mapping for active capabilities CAP-001 through CAP-017)
  - `docs/QUALITY-GATES.md` ✅ Complete (Full 5-tier translation: Pint, Larastan 8, cspell, markdownlint, gitleaks, fail-closed write-safety, registration whitelist tests, Playwright layout, Diff-Cover 90%, deploy workflow_run gating)
  - `.github/workflows/ci.yml` ✅ Complete (Automated PR gate with all 5 verification tiers)
  - `.github/workflows/deploy.yml` ✅ Complete (Gate 5 safe deployment workflow_run trigger)
  - `.cspell.json` ✅ Complete (Lexicon and spelling hygiene dictionary)

## Active Invariants & Boundaries
1. **Email Whitelist Registration Gate**: Account registration is strictly restricted to pre-approved corporate emails (`allowed_registration_emails`), preventing unauthorized public signups ([`ADR-013`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/docs/adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)).
2. **Sovereign Workspace Isolation**: Every whitelisted executive receives their own private Outlook mailbox credentials, chat sessions, personal notes, tasks, and presets with zero inter-user data leakage.
3. **Shared Enterprise Project Register**: Processed summaries and extracted commitments compound into a shared, company-wide SQLite FTS5 index with author attribution.
4. **Zero Raw Email Storage**: The app does not replicate Outlook or store raw emails; it queries Outlook on-demand via `outlook-mcp` (Graph API) and stores only processed outputs (summaries, commitments, notes, tasks).
5. **ARH Session Reader Memory Engine**: SQLite FTS5 full-text indexing + RRF fusion + decision markers (`dm:decision`, `dm:commitment`) across project registers and action receipts.
6. **Explicit Write Confirmation**: AI generates interactive Action Cards for drafting, replying, and forwarding; execution requires human approval with signed one-time tokens before Graph API dispatch.
7. **High-Density Memory Output**: Token-efficient pipe-delimited context formatting to inject decades of project memory into <500 tokens.

## Single Next Entry Point
- Scaffold the Laravel 12 + Filament v4 application skeleton into `D:\ARH-GITHUB\arhsmoque2\dpik-tadbir`.
