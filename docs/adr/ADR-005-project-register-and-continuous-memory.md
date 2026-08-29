# ADR-005: Project Register & Continuous Context Intelligence Accumulation

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
As the AI processes emails and executive queries, vital context—such as agreed deadline shifts, site survey updates, and client commitments—is extracted. If this knowledge is stored only in transient chat sessions or unstructured text files, it is quickly lost. To build compounding familiarity with ongoing company projects, the system requires a structured **Project Register** that categorizes and accumulates domain knowledge automatically.

## Decision
1. **Persistent Project Register Store (`project_registry_entries`)**:
   - Create a normalized relational store linked directly to `projects.id`.
   - Store: `project_id`, `source_type` (`outlook_search`, `email_summary`, `site_briefing`), `source_reference_id`, `title`, `summary`, `key_commitments` (JSON array), `action_items` (JSON array), and `dm_tags` (decision-marker array).
2. **Configurable Categorization & Keyword Matching Rules**:
   - **Project Code Regex Engine**: Configurable regex patterns (e.g. `PC-\d{4}-\d{3}`, `[A-Z]{2,4}-\d{2,4}`) to auto-detect project references in email text.
   - **Project Keyword & Alias Mappings**: Editable lookup table mapping informal names to official project codes (e.g. `"Sungai Udang"` $\rightarrow$ `PC-2023-011`, `"Sedenak"` $\rightarrow$ `PC-2023-015`).
   - **Auto-Categorization Confidence Threshold**: Configurable slider (default `80%`) governing when the AI proposes automatic project tagging.
3. **Decision & Commitment Marker Tags (`dm:`)**:
   - Standardize heuristic marker tags across all entries:
     - `dm:decision` (approved variations, sign-offs)
     - `dm:commitment` (deliverable deadlines, promised submittals)
     - `dm:blocker` (authority delays, site access issues)
     - `dm:financial` (invoices, claims, fee agreements)
   - Keywords triggering these markers are fully editable in the Settings Panel.

## Consequences
- **Positive**: The AI accumulates deep domain memory; future queries about any project retrieve accurate historical context instantly.
- **Positive**: Projects retain an official, chronological history of executive correspondence.
- **Trade-off**: Requires periodic pruning or archiving of superseded project milestones.
