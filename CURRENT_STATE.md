# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: Design, Architecture, CI/CD Quality Gates & Test Coverage Plan Complete $\rightarrow$ Ready for Codebase Scaffolding.
- **Governing ADR**: `docs/adr/ADR-001-stack-selection.md` (Accepted: Laravel 12 + Filament v4 + MCP + Resonator).
- **Progressive Lifecycle Specs**:
  - `docs/INTENT.md` ✅ Complete
  - `docs/SCENARIOS.md` ✅ Complete
  - `docs/CAPABILITIES.md` ✅ Complete
  - `docs/ARCHITECTURE.md` ✅ Complete
  - `docs/UI.md` ✅ Complete
  - `docs/ui-spec/navigation-tree.json` ✅ Complete
  - `docs/ui-spec/flow-diagram.mermaid` ✅ Complete
  - `docs/testing/coverage-risk-matrix.json` ✅ Complete (Tier 1-4 risk mapping)
  - `docs/testing/test-plan.md` ✅ Complete (Spec-to-test verification)
  - `docs/QUALITY-GATES.md` ✅ Complete (Pint, Larastan Level 8, Diff-Cover)
  - `.github/workflows/ci.yml` ✅ Complete (Automated PR gate with 90% diff coverage)

## Active Invariants & Boundaries
1. In-app AI Agent uses `ARH-URUS` `AgentService` + `ToolRegistry` bridged to `laravel/mcp`.
2. Outlook email connectivity uses `mpalermiti/outlook-mcp` via OS Credential Store.
3. Visual Inbox uses `ekandreas/filament-resonator` schema.
4. Personal Notes & Tasks are strictly user-isolated.
5. All PRs must achieve ≥90% branch coverage on modified lines.

## Single Next Entry Point
- Scaffold the Laravel 12 + Filament v4 application skeleton into `D:\_ARH-AGENT-OS\projects\dpik-tadbir`.
