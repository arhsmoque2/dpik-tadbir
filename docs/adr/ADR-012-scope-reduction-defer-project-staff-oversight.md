# ADR-012: Scope Reduction & Deferral of Project, Staff Oversight, and Ticketing Modules

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
During initial scoping and research (`docs/research/PR-001-scouting-and-reusable-modules.md`), full project and staff management capabilities (departments, positions, ticketing, epics, and workload balancing from `dpik-tugas-laravel`) were evaluated for inclusion in DPIK Tadbir.

However, during active design and refinement, the core operational focus narrowed strictly to an **AI-assisted executive command center and Outlook email intelligence processor** for the Managing Director. Attempting to build full enterprise project management, staff organizational hierarchies, and ticketing alongside the core Outlook MCP agent and FTS5 project memory engine introduces premature architectural bloat and multi-user complexity before the core executive email processing loop is verified in production.

## Decision

1. **Defer CAP-008 (Project & Staff Oversight) and CAP-009 (Visual Command Center Scope)**:
   - Project management, organizational departments, staff position assignments, epics, and ticketing are formally **deferred (Keep In View / KIV)** to a subsequent phase.
   - These modules are **not cancelled or rejected**; all domain contracts, schema models, and service signatures remain documented for future resumption.

2. **Strict Single-User / Personal Executive Posture**:
   - The application is scoped as a **single-user personal command center** for the Managing Director operating under the `super_admin` role.
   - Multi-role internal RBAC (`project_manager`, `staff`, `hr`) is placed on hold (KIV).
   - Any future organizational distinction between HQ-based personnel and external consultants/site staff will be handled via a lightweight boolean/enum attribute (`is_hq`), not an active multi-tier permission system.

3. **External MCP Access Boundary Retained**:
   - Access control is enforced exclusively at the **external MCP server boundary** (`/mcp` endpoint), where external coding agents (Cursor, Claude Code) authenticate via scoped bearer tokens to access exposed tools and project memory.

4. **Project Register (CAP-005) Stays Intact**:
   - The **Project Register** (`project_registry_entries`, SQLite FTS5 hybrid search) is part of the core email memory and knowledge accumulation engine. It remains fully active and is not affected by the deferral of project/staff ticketing.

5. **Future Commercial Positioning Captured in `docs/OPPORTUNITIES.md`**:
   - Forward-looking concepts—including the **"Tugas"** domain umbrella branding (OPP-001), the **Aksi / Deliverable / PIC** engineering workflow model, and the **Staff Workload Visibility** pipeline with HR sensitivity guardrails (OPP-002)—are formally preserved in `docs/OPPORTUNITIES.md`.

## Consequences
- **Positive**: Radical reduction in initial build complexity, schema migrations, and testing surface.
- **Positive**: Focused execution on the high-value Outlook MCP bridge, zero-storage email processor, write-safety action cards, and SQLite FTS5 hybrid memory retrieval.
- **Positive**: Zero orphan status—deferred capabilities are cleanly isolated with clear resumption criteria.
- **Trade-off**: Staff capacity balancing and ticketing delegation remain manual/informal until Phase 2 resumption.
