# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: Design, Architecture, CI/CD Quality Gates & Test Coverage Plan Complete $\rightarrow$ Ready for Codebase Scaffolding.
- **Governing ADRs (ADR-001 through ADR-011)**:
  - `docs/adr/ADR-001-stack-selection.md` (Laravel 12 + Filament v4 + MCP; zero local raw email storage)
  - `docs/adr/ADR-002-ai-model-and-provider-governance.md` (Multi-Provider, Fallbacks, Prompts)
  - `docs/adr/ADR-003-outlook-mcp-email-processor-boundary.md` (Zero Raw Email Storage Boundary)
  - `docs/adr/ADR-004-executive-presets-and-quick-action-engine.md` (Custom Presets & Templating)
  - `docs/adr/ADR-005-project-register-and-continuous-memory.md` (Project Register & Domain Memory)
  - `docs/adr/ADR-006-hybrid-memory-search-and-retrieval-engine.md` (SQLite FTS5, RRF, Dense Context)
  - `docs/adr/ADR-007-write-safety-human-in-the-loop-approval-gates.md` (Action Cards, Approval Gates)
  - `docs/adr/ADR-008-action-receipts-and-automated-activity-rollups.md` (Action Ledger & Rollups)
  - `docs/adr/ADR-009-system-settings-and-runtime-configurability.md` (Zero-Hardcoding Settings Store)
  - `docs/adr/ADR-010-continual-executive-personalization-engine.md` (Behavioral Adaptation & Executive Persona Profile)
  - `docs/adr/ADR-011-interactive-ui-modals-and-human-in-the-loop-tools.md` (Interactive Modals, Choice Pickers & State Machine)
- **Progressive Lifecycle Specs**:
  - `docs/INTENT.md` ✅ Complete (Outlook MCP Processor, Zero Raw Storage, Presets, Project Memory)
  - `docs/SCENARIOS.md` ✅ Complete (On-Demand Inbox Checks, Presets, Reply/Forward Confirmation, Project Register, Activity Rollups, Workload Rebalancing, Multi-Role RBAC Privacy)
  - `docs/CAPABILITIES.md` ✅ Complete (CAP-001 through CAP-016 fully reconciled)
  - `docs/ARCHITECTURE.md` ✅ Complete (Outlook MCP Bridge, SQLite FTS5 Indexing, RRF Reranking, Token-Dense Context Contract, Reconciled MCP Tool Hierarchy)
  - `docs/DESIGN.md` ✅ Complete (8 Subsystems, State Authority, Reconciled Service Signatures, Project & Staff Oversight, Livewire State Machine)
  - `docs/UI.md` ✅ Complete (Executive Assistant, Preset Chips, Action Cards, Project Register, Activity Rollups)
  - `docs/ui-spec/navigation-tree.json` ✅ Complete
  - `docs/ui-spec/flow-diagram.mermaid` ✅ Complete
  - `docs/testing/coverage-risk-matrix.json` ✅ Complete (Unified 4-tier risk matrix aligned with DESIGN.md)
  - `docs/testing/test-plan.md` ✅ Complete (Spec-to-Test mapping for CAP-001 through CAP-016)
  - `docs/QUALITY-GATES.md` ✅ Complete (Full 5-tier translation: Pint, Larastan 8, cspell, markdownlint, gitleaks, fail-closed write-safety, Playwright layout, Diff-Cover 90%, deploy workflow_run gating)
  - `.github/workflows/ci.yml` ✅ Complete (Automated PR gate with all 5 verification tiers)
  - `.github/workflows/deploy.yml` ✅ Complete (Gate 5 safe deployment workflow_run trigger)
  - `.cspell.json` ✅ Complete (Lexicon and spelling hygiene dictionary)

## Active Invariants & Boundaries
1. **Zero Raw Email Storage**: The app does not replicate Outlook or store raw emails; it queries Outlook on-demand via `outlook-mcp` (Graph API) and stores only processed outputs (summaries, commitments, notes, tasks).
2. **ARH Session Reader Memory Engine**: SQLite FTS5 full-text indexing + RRF fusion + decision markers (`dm:decision`, `dm:commitment`) across project registers and action receipts.
3. **One-Click Presets**: Instant preset triggers (*"What's new today?"*, *"Check my email today"*, *"Action items needing reply"*).
4. **Explicit Write Confirmation**: AI generates interactive Action Cards for drafting, replying, forwarding, and reassigning tickets; execution requires human approval with signed one-time tokens before Graph API dispatch.
5. **High-Density Memory Output**: Token-efficient pipe-delimited context formatting to inject decades of project memory into <500 tokens.
6. **Multi-Role Tenant Scoping (RBAC)**: Personal notes/tasks isolated strictly per `auth()->id()`; project/staff oversight accessible according to role permissions.

## Single Next Entry Point
- Scaffold the Laravel 12 + Filament v4 application skeleton into `D:\ARH-GITHUB\arhsmoque2\dpik-tadbir`.
