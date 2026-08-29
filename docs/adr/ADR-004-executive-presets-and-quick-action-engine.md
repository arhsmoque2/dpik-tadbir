# ADR-004: Executive Presets & Dynamic Quick Action Engine

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Leadership interacts with executive intelligence primarily through recurring queries (e.g. *"What's new today?"*, *"Check my email today"*, *"Action items needing reply"*, *"Project progress & blockers"*). Hardcoding these buttons or prompts restricts flexibility as company operations and priorities evolve. A dynamic preset management engine is required to allow leadership to define, modify, reorder, and style preset action chips without writing code.

## Decision
1. **Database-Driven Preset Model (`executive_presets`)**:
   - Create a dedicated model and Filament settings resource for managing executive presets.
2. **Schema & Configurable Attributes**:
   - **`title` & `slug`**: Display name and identifier (e.g. `"What's new today?"`, `whats-new-today`).
   - **`icon` & `color`**: Visual heroicon and badge color for UI rendering on the Executive Preset Bar.
   - **`prompt_template`**: Parameterized prompt instruction sent to the LLM (e.g. *"Perform a delta scan of unread Outlook emails from the past {lookback_window}. Highlight critical client items in 3 bullets."*).
   - **`lookback_window`**: Query duration parameter (`today`, `24h`, `48h`, `7d`, `custom`).
   - **`target_scope`**: Data scope filter (`inbox_delta`, `active_projects`, `open_tickets`, `combined`).
   - **`output_format`**: Expected presentation structure (`executive_bullets`, `action_table`, `checklist`).
   - **`sort_order` & `is_active`**: Controls display order and visibility on the header bar.
3. **Execution Pipeline**:
   - Clicking a preset chip triggers `PresetExecutionService`, interpolating runtime variables into the template and streaming the synthesized result into the AI Assistant drawer.

## Consequences
- **Positive**: Leadership can tailor prompts and create new domain-specific presets (e.g. *"Tender Review Morning Scan"*) directly in settings.
- **Positive**: Consistent formatting across all recurring daily and weekly queries.
- **Trade-off**: Requires input sanitization on prompt template variables.
