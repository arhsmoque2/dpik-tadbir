# DPIK Tadbir: Opportunities & Future Considerations

This document records deliberate, prospective architectural directions and commercial opportunities, distinct from `docs/GAPS.md` (which tracks open defects and missing contracts in the active milestone).

Each opportunity pairs its strategic upside and domain moat with mandatory **Non-Negotiable Guardrails** established *prior* to scheduling or implementation, ensuring critical policy, naming, and architectural constraints are never compromised under delivery pressure.

---

## [OPP-001] "Tugas" as Local-Market Brand Positioning

### Strategic Context & Opportunity
If DPIK Tadbir expands into a commercial multi-tenant SaaS offering, competing head-on with horizontal enterprise work management tools (Asana, Monday.com, ClickUp) on raw feature breadth is an inefficient strategy. Horizontal platforms cater to generic cross-industry workflows, resulting in bloated surface areas that lack native alignment with Malaysian engineering consultancies, project management firms, and infrastructure contractors (e.g., Minco, Perunding Zaaba, HSS Integrated).

Naming the umbrella work-item taxonomy **"Tugas"** (instead of a generic "Task" or "Ticket") immediately signals to non-technical Malaysian consultants and executives that the platform is purpose-built for local industry operations, rather than localized from an offshore SaaS template.

### The True Domain Moat
The competitive advantage is not the Malay terminology itself, but the domain-specific workflow mechanics operating beneath it:
1. **Deliverable Revision Cycles Tied to Financial Claims**: Tracking engineering drawing revisions ($R_0, R_1, R_2, \dots, R_n$) directly against interim payment certificates and consultant fee schedules.
2. **Distinct Governance vs. Execution Roles**: Explicit separation between the **Person-In-Charge (PIC)** (holding formal delivery governance, review responsibility, and client accountability) and hands-on operational executors.
3. **Tender-Briefing & Site-Action Workflows**: Fast-track, lightweight action item capture designed for pre-bid site visits, technical clarification meetings, and statutory authority submissions.

Horizontal tools lack native data models for these specialized engineering flows. "Tugas" serves as the cultural and operational entry point that highlights this differentiated domain layer.

### Non-Negotiable Guardrails
- **Product & Marketing Surface Only**: "Tugas" must exist exclusively in user-facing navigation labels, document titles, and marketing positioning copy. All underlying database schemas, Eloquent models, API routes, MCP tool contracts, and class names must remain in plain English (e.g., `actions`, `deliverables`, `pic_assignments`, `personal_tasks`, `tickets`, not `tugas_id` or `TugasController`).
- **Architectural Precedent (ARH-URUS ADR-0004)**: Follow the established architectural standard set in ARH-URUS `ADR-0004`, where internal "Kinhold" identifiers were deliberately preserved during the "Urus" brand transition. Renaming internal codebase constructs for branding reasons introduces unverified regression risks and high refactoring debt. Any internal renaming must be scoped as a dedicated refactoring task, never executed as an incidental side-effect of feature work.
- **English Functional Entities**: Specific functional entities (e.g., "Action", "Deliverable", "Milestone", "Receipt") must remain in English to maintain cognitive clarity, avoid bilingual fragmentation across technical interfaces, and preserve international system exportability. Only the top-level umbrella concept carries the "Tugas" label.
- **Zero Overhead on Personal Command Center**: This is a strategic positioning decision for prospective commercialization. It must introduce zero schema churn or operational overhead while the platform operates in its primary personal executive mode for the Managing Director.

---

## [OPP-002] Staff Workload Visibility & Longitudinal Performance Data

### Strategic Context & Opportunity
Currently, staff task allocations and workload distribution operate informally across unstructured communication channels (e.g., WhatsApp groups, verbal check-ins), leaving leadership without a real-time, birds-eye view of team bandwidth.

A structured **Staff Workload Visibility Dashboard** (aggregating Actions, Deliverables, and PIC governance assignments per team member, weighted by an explicit capacity matrix: $\text{PIC Governance} > \text{Deliverable Review / Authoring} > \text{Action Items}$) provides immediate operational utility for daily briefings, capacity balancing, and bottleneck mitigation.

Over the long term, this timestamped, auditable activity stream could provide an empirical foundation for talent retention, bonus evaluations, and promotion reviews—providing objective operational receipts that mitigate subjective bias in managerial assessments.

### Non-Negotiable Guardrails (HR Sensitivity & Policy Boundaries)
- **Presentation Only — Zero Algorithmic Judgment**: The system presents aggregated workload distributions; **it must never auto-rank staff, calculate automated "underperformance" flags, or recommend personnel actions (termination, demotion, reprimand)**. In alignment with `ADR-007` (Write Safety) and ethical AI governance, all evaluative judgment remains exclusively with human leadership. Automated ranking widgets (e.g., "Least Productive Staff" lists) are strictly prohibited.
- **Immutable Audit Trail for Weight Matrix Configurations**: Any modification to the workload weighting matrix (e.g., altering the relative weight of PIC roles versus Deliverables) must generate an immutable, timestamped record in the audit log (`AuditLog` / `AiActionReceipt`), capturing the actor ID, timestamp, and before/after parameter state. Weighting parameters must not be quietly adjusted prior to performance review cycles without an immutable audit trail.
- **Cognitive Framing: Volume/Capacity $\neq$ Performance**: The UI must explicitly display metrics as a **Capacity / Volume Allocation Index**, never as a "Performance" or "Productivity" score. A linear, quantity-weighted index inherently conflates volume with value: a senior engineer conducting a high-stakes peer review on a single critical bridge calculation might show lower item volume than a junior executing ten routine coordination tasks, yet delivers equal or greater enterprise value. Presenting volume as performance reintroduces cognitive bias under the guise of algorithmic objectivity.
- **Unresolved Algorithmic Parameters**: The normalized capacity scale (e.g., 0–1 index vs. open hours), over-capacity indicators, and dynamic weighting coefficients are formally classified as unresolved. They must undergo empirical calibration and operator testing before being locked into the codebase.

---

## Lifecycle Metadata

- **Status**: `Aspirational — Not Scheduled`
- **Scope Commitment**: None (Speculative / Exploratory only)
- **Governing Invariant**: No database migrations, Eloquent schema updates, or API route changes are to be executed based on this document until formally promoted via a dedicated Architectural Decision Record (ADR).
