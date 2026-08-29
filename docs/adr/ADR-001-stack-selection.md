# ADR-001: Technology Stack Selection for DPIK Tadbir

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
DPIK Tadbir requires a rapid, robust full-stack solution to unite executive email processing (Outlook/Gmail), company project & staff management (extracted from Tugas), and an AI conversational agent (from ARH-URUS).

We evaluated:
1. **Laravel 12 + Filament v4 (TALL Stack)** vs. **Next.js / Node.js**
2. **Filament v4** vs. **Filament v5**
3. **In-process MCP Client (`laravel/mcp` + `ToolRegistry`)** vs. **Ad-hoc Custom API wrappers**

## Decision
1. **Adopt Laravel 12 with Filament v4**:
   - `filament-resonator` UI components natively require Filament `^4.0` for Livewire email thread visualization and action drawer styling over on-demand MCP data.
   - `dpik-tugas-laravel` models and resources are already written for Filament v4.
   - Filament provides out-of-the-box forms, tables, modals, action drawers, and notifications with zero boilerplate.
2. **Adopt `laravel/mcp` + `ARH-URUS` `ToolRegistry` Pattern**:
   - Allows writing single standard `Tool` classes that run internally inside the Laravel web chat AND can be served via `/mcp` to external desktop coding agents (Cursor, Claude Code).
3. **Adopt `outlook-mcp` for Graph API Operations**:
   - Python-based server storing tokens in Windows Credential Store, providing typed tools with concise mode and delta synchronization.
   - In accordance with ADR-003, Tadbir stores zero raw email bodies or attachments locally; `filament-resonator` serves strictly as a presentation surface for on-demand MCP data.

## Consequences
- **Positive**: 100% component reuse from `filament-resonator`, `ARH-URUS`, and `dpik-tugas-laravel` without version friction.
- **Positive**: Single codebase handles the full admin panel, database persistence, and MCP tools.
- **Trade-off**: Requires local Python runtime (`uv`) to execute the `outlook-mcp` bridge daemon.
