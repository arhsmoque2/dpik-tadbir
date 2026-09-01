# ADR-026: Live AI & MCP Control Plane (`ai-configuration.json`) and Hot-Reloading Architecture

**Status**: Accepted  
**Date**: 2026-09-01  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
ADR-009 established the zero-hardcoding contract for system settings. However, AI system prompt templates, anti-hallucination rules, context mode token budgets, temperature, memory retrieval depths, and individual MCP tool switches were previously scattered across PHP class constants and settings classes. Tuning prompts or disabling tools required code changes. The system requires a centralized, developer-friendly control plane (similar to `settings.json` in modern developer tooling) accessible by Super Admins in the UI.

## Decision
1. **Centralized Configuration Subsystem (`AiConfigurationService`)**:
   - Stores the complete unified runtime configuration in the `settings` database table under group `ai_control_plane` and key `runtime_json`.
   - Structured JSON schema covering:
     - `system_prompt` (`base_template`, `rules` array)
     - `ai_tuning` (`temperature`, `max_iterations`, `context_mode_profiles` dictionary)
     - `memory_settings` (`rrf_k`, `search_limit`, `dense_context_max_chars`)
     - `mcp_servers` (`imap`, `outlook`, `memory`, `notes` configurations)
     - `tools` (per-tool `enabled` and `requires_confirmation` flags)
2. **Immutable Factory Defaults & Fallback Guard**:
   - `getFactoryDefaults()` provides a robust baseline. If database records are missing or corrupted, the system falls back safely to factory defaults without runtime crashes.
3. **Hot-Reloading in `AgentService`**:
   - `AgentService` dynamically pulls template, rules, context budgets, and iteration bounds from `AiConfigurationService` on every turn.
   - Cache is flushed immediately upon saving, applying changes instantaneously without restarting application containers.
4. **Filament Super Admin JSON Editor**:
   - Integrated into `ExecutiveSettings.php` (gated strictly to `super_admin` users).
   - Features a syntax-friendly dark monospace editor, **Format JSON** action, **Reset to Defaults** action, and JSON schema validation.

## Consequences
- **Positive**: 100% live tunability of prompts, rules, budgets, and tools directly from the web interface.
- **Positive**: Zero code deployments or container restarts needed to adjust AI behavior.
- **Positive**: Safe "Reset to Defaults" button prevents configuration lockouts.
- **Trade-off**: Requires superadmin discipline to avoid breaking system prompt placeholder syntax (`{executive_name}`, `{tools}`, `{memory}`).
