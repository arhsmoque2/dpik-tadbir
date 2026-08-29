# ADR-009: Database-Backed Settings Architecture & Zero-Hardcoding Contract

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Across all 8 preceding architectural decisions, every threshold, prompt, duration, model choice, preset, and safety gate must be dynamically configurable at runtime without modifying code or restarting application daemons. Hardcoding values in application code violates operational agility and requires engineering intervention for simple business adjustments.

## Decision
1. **Database-Backed Settings Subsystem**:
   - Implement typed settings repositories (using `spatie/laravel-settings` or a dedicated `system_settings` table) categorized by distinct namespaces:
     - `AiSettings` (models, providers, temperature, token limits, system prompts)
     - `OutlookSettings` (bridge paths, fetch limits, lookback windows, folder filters, sender domains)
     - `PresetSettings` (custom preset models, prompt templates, display ordering)
     - `ProjectRegisterSettings` (regex rules, aliases, confidence thresholds, decision markers)
     - `SearchSettings` (RRF damping constant $k$, time decay, context chunk limits)
     - `SafetySettings` (approval policies, pre-approved domains, email signatures)
     - `AuditSettings` (rollup schedules, retention periods, cost logging)
2. **Filament Control Plane Integration**:
   - Create a dedicated **Settings Resource** in Filament featuring a tabbed interface corresponding to each namespace.
   - Values are validated using typed rules (e.g. valid regex for project patterns, valid float ranges for temperature).
3. **Hot-Reloading & In-Memory Caching**:
   - Settings are cached in fast local memory/Redis and flushed immediately upon saving in the Filament panel, applying instantly to subsequent AI requests without server restarts.

## Consequences
- **Positive**: Complete user sovereignty; 100% of operational levers are manageable directly through the web UI.
- **Positive**: Zero code deployments needed to adjust AI prompts, add project code patterns, or tweak safety policies.
- **Trade-off**: Requires database seeding with authoritative defaults on initial installation.
