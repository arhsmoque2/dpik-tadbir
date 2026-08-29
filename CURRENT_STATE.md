# DPIK Tadbir: Current State

## Verified Reality Snapshot
- **Lifecycle Phase**: Design & Architecture Complete $\rightarrow$ Framework Scaffolding Ready.
- **Governing ADR**: `docs/adr/ADR-001-stack-selection.md` (Accepted: Laravel 12 + Filament v4 + MCP + Resonator).
- **Progressive Lifecycle Specs**:
  - `docs/INTENT.md` ✅ Complete
  - `docs/SCENARIOS.md` ✅ Complete
  - `docs/CAPABILITIES.md` ✅ Complete
  - `docs/ARCHITECTURE.md` ✅ Complete
  - `docs/UI.md` ✅ Complete
  - `docs/ui-spec/navigation-tree.json` ✅ Complete
  - `docs/ui-spec/flow-diagram.mermaid` ✅ Complete

## Active Invariants & Boundaries
1. In-app AI Agent uses `ARH-URUS` `AgentService` + `ToolRegistry` bridged to `laravel/mcp`.
2. Outlook email connectivity uses `mpalermiti/outlook-mcp` via OS Credential Store.
3. Visual Inbox uses `ekandreas/filament-resonator` schema.
4. Personal Notes & Tasks are strictly user-isolated.

## Single Next Entry Point
- Scaffold the Laravel 12 skeleton with Filament v4 in `D:\_ARH-AGENT-OS\projects\dpik-tadbir` and commit initial codebase.
