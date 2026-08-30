# ADR-017: Runtime Integration Settings, Live Reflection Probes & Graceful Provider-Direct Error Guards

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

---

## Context

DPIK Tadbir previously required setting Microsoft Graph credentials (`MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_TENANT_ID`) as static cloud environment variables during container deployment, while AI API keys were configured per-executive in `ExecutiveSettings`. 

This asymmetric configuration approach created several operational issues:
1. **Deployment Friction**: If an executive or administrator did not yet have an Azure Entra ID App Registration, deployment was blocked or required container rebuilds when credentials became available.
2. **Multi-Tenant / Multi-Executive Rigidity**: Different executives could not configure distinct Outlook mailbox integrations or change credentials on-demand without touching infrastructure.
3. **Cryptographic Inconsistency**: Storing Microsoft secrets in cloud environment variables bypassed the sovereign, per-user at-rest AES-256 database encryption model used for AI keys ([`ADR-013`](ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)).
4. **Poor Error UX**: When users entered malformed keys, expired secrets, or lacked tenant permissions, standard web frameworks either crashed or returned generic 500 errors without actionable guidance.

---

## Decision

1. **Sovereign UI-Driven Integration Configuration**:
   - Move all external service credentials (Anthropic Claude 3.7, Google Gemini 2.5, and Microsoft 365 / Azure Entra ID) into the **Executive Settings** interface (`/admin/executive-settings`).
   - Store `microsoft_client_secret` encrypted at rest in the `users` table via Laravel's `encrypted` cast (`AES-256-CBC` via `APP_KEY`), alongside `anthropic_api_key` and `gemini_api_key`.
   - Maintain a 3-tier resolution hierarchy:
     1. **User/Executive Settings** (highest priority, isolated to `auth()->id()`)
     2. **System Database Settings** (`OutlookSettings` / `AiSettings` via Spatie Laravel Settings)
     3. **Environment Variables** (`.env` / Cloud Run as fallback)

2. **Real-Time State Reflection & Verification Loop**:
   - Trigger instant cache eviction (`SettingsContainer::clearCache()`) and rehydrate authenticated user state upon saving.
   - Dispatch Livewire events (`executive-settings-saved`, `outlook-status-changed`) to immediately update topbar status badges and Copilot drawer ribbons without requiring full-page reloads.
   - Provide an interactive **Test Connection** button that runs ephemeral 1-token / OAuth probes against Anthropic, Gemini, and Microsoft Graph.

3. **Provider-Direct Error Diagnostics & Actionable Fix Guides**:
   - Intercept exact upstream error responses (e.g. Anthropic `invalid_x_api_key`, Gemini `API_KEY_INVALID`, Microsoft Entra `AADSTS700016`, `AADSTS7000215`, `ErrorAccessDenied`).
   - Display the exact upstream error message inside high-visibility UI alert cards alongside step-by-step instructions on how to obtain the correct values from Azure Portal or AI consoles.
   - Validate syntax and formats (UUID v4 regex, key prefixes) client-side and server-side before initiating network requests.

4. **Graceful Degradation Engine**:
   - If Microsoft Graph credentials fail authentication or are missing, the system degrades gracefully:
     - All general AI reasoning, Project Register search (FTS5), Personal Notes, Tasks, and Presets remain 100% active.
     - Email-specific tools fail closed with a friendly, sanitized explanation pointing the user to the Settings page.
   - If the primary AI provider (Anthropic) fails, the gateway automatically falls back to Google Gemini 2.5 Flash without dropping the user's conversation prompt.

---

## Consequences

- **Positive**: Zero deployment friction; Tadbir can deploy immediately with zero initial Azure credentials and be configured live in under 5 minutes.
- **Positive**: Cryptographically secure sovereign isolation for both AI and Microsoft Graph secrets.
- **Positive**: Drastically reduced support overhead; users receive exact error causes and resolution links directly within the UI.
- **Positive**: Complete sync across [`docs/CONFIGURABLES.md`](../CONFIGURABLES.md), [`docs/DESIGN.md`](../DESIGN.md), and [`docs/UI.md`](../UI.md).
- **Trade-off**: Requires database migration to extend the `users` table schema and maintain diagnostic probe endpoint handlers.
