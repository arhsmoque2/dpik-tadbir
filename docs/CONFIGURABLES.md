# DPIK Tadbir: Configurable Variables, UI Integration Plan & Error Guard Architecture

This document defines the comprehensive inventory of all configurable variables in DPIK Tadbir, the UI/UX architecture linking settings to the main executive interface, real-time reflection verification loops, and strict error guards featuring provider-direct diagnostics and graceful fallback instructions.

---

## 1. Inventory of Configurable Variables

DPIK Tadbir supports a dual-tier configuration architecture:
1. **Executive-Tier (User Scoped)**: Stored per-executive in the `users` database table with at-rest AES-256-CBC encryption (`encrypted` cast via `APP_KEY`). Enables sovereign credentials and isolated workspaces per [ADR-013](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md).
2. **System-Tier (Tenant / Global)**: Stored via Spatie Laravel Settings in PostgreSQL or `.env` fallback.

```text
+----------------------------------------------------------------------------------------------------+
|                                    CONFIGURATION ARCHITECTURE                                      |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|   Tier 1: Executive Sovereign Store (users table - Encrypted)                                      |
|   ├── anthropic_api_key (Claude 3.7 Sonnet)                                                        |
|   ├── gemini_api_key (Gemini 2.5 Flash)                                                            |
|   ├── microsoft_client_id (Azure Entra App ID)                                                     |
|   ├── microsoft_client_secret (Azure Entra Secret - Encrypted)                                     |
|   └── microsoft_tenant_id (Azure Tenant ID)                                                        |
|                                                                                                    |
|   Tier 2: System & Engine Settings (spatie/laravel-settings & config)                              |
|   ├── ai.default_provider & ai.fallback_provider                                                   |
|   ├── memory.fts5_token_budget & memory.rrf_k_factor                                               |
|   ├── outlook.default_lookback_hours & outlook.default_page_size                                   |
|   └── auth.allowed_registration_emails                                                             |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```

### 1.1 Detailed Variable Matrix

| Variable Identifier | Storage Scope | Encryption | UI Location | Default / Fallback | Allowed Values / Format | Description |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `anthropic_api_key` | User (`users`) | AES-256 | `/admin/executive-settings` | System SOPS fallback | Starts with `sk-ant-api03-...` (≥90 chars) | Primary LLM key for Claude 3.7 Sonnet executive turns and Action Card drafting. |
| `gemini_api_key` | User (`users`) | AES-256 | `/admin/executive-settings` | System SOPS fallback | Starts with `AIzaSy...` (39 chars) | Secondary fallback LLM key triggered automatically on Anthropic rate limits or timeouts. |
| `openrouter_api_key` | User (`users`) | AES-256 | `/admin/executive-settings` | None | Starts with `sk-or-v1-...` | Unified API key for OpenRouter multi-model catalog (Claude, DeepSeek, GPT-4o, Llama). |
| `favorite_model_1` | User (`users`) | Plain Text | `/admin/executive-settings` | `anthropic:claude-3-7-sonnet-20250219` | Provider & Model Tuple | Primary favorite brain for default executive reasoning turns. |
| `favorite_model_2` | User (`users`) | Plain Text | `/admin/executive-settings` | `openrouter:deepseek/deepseek-r1` | Provider & Model Tuple | Secondary favorite brain for logic, calculations, and code review. |
| `favorite_model_3` | User (`users`) | Plain Text | `/admin/executive-settings` | `gemini:gemini-2.5-flash` | Provider & Model Tuple | Tertiary favorite brain for ultra high-speed batch summaries. |
| `microsoft_client_id` | User / System | Plain Text | `/admin/executive-settings` | `env('MICROSOFT_CLIENT_ID')` | UUID v4 (`^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`) | Azure Entra ID Application (Client) ID for Microsoft Graph. |
| `microsoft_client_secret` | User / System | AES-256 | `/admin/executive-settings` | `env('MICROSOFT_CLIENT_SECRET')` | Alphanumeric secret string (~34-40 chars) | Azure Entra ID client secret value for mailbox OAuth authentication. |
| `microsoft_tenant_id` | User / System | Plain Text | `/admin/executive-settings` | `env('MICROSOFT_TENANT_ID')` | UUID v4 or `organizations` / `common` | Azure Directory (Tenant) ID for corporate M365 tenant boundary. |
| `ai_default_model` | System | Plain Text | `/admin/settings/ai` | `claude-3-7-sonnet-20250219` | Valid Anthropic model string | Default reasoning model for primary synthesis turns. |
| `ai_fallback_model` | System | Plain Text | `/admin/settings/ai` | `gemini-2.5-flash` | Valid Google Gemini model string | Failover model for resilient turns under upstream degradations. |
| `ai_temperature` | User / System | Plain Text | `/admin/settings/ai` | `0.2` | Float between `0.0` and `1.0` | Sampling temperature; low values ensure deterministic extraction. |
| `memory_token_budget` | System | Plain Text | `/admin/settings/memory` | `500` | Integer between `200` and `2000` | Maximum token ceiling for dense pipe-delimited context injection. |
| `memory_rrf_k_factor` | System | Plain Text | `/admin/settings/memory` | `60` | Integer between `1` and `100` | Reciprocal Rank Fusion smoothing constant for SQLite FTS5 retrieval. |
| `outlook_lookback_hours` | User / System | Plain Text | `/admin/settings/outlook` | `24` | Integer (`1` to `168`) | Default lookback horizon when querying unread inbox delta. |
| `outlook_page_size` | User / System | Plain Text | `/admin/settings/outlook` | `25` | Integer (`5` to `100`) | Maximum email items retrieved per MCP tool call. |
| `allowed_registration_emails` | System (`allowed_registration_emails` table) | Plain Text | `/admin/allowed-registration-emails` | Seeded super admin list | Valid email address (`user@domain.com`) | Whitelisted corporate emails authorized to register and access Tadbir. |

---

## 2. Linking Plan: Settings to Main Page & Copilot Drawer

Configurations must seamlessly connect to the operational workspace without requiring full page refreshes or manual server restarts.

```text
+----------------------------------------------------------------------------------------------------+
|                                    UI LINKING & REACTIVE FLOW                                      |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|   1. Executive Settings UI (/admin/executive-settings)                                             |
|      ├── Input credentials (AI API Keys, Microsoft Entra IDs)                                      |
|      ├── Interactive "Test Connection" button with live diagnostics                                |
|      └── Dispatches Livewire Event: `executive-settings-saved`                                      |
|                                                                                                    |
|   2. Global Navigation Topbar (Filament Panels)                                                    |
|      ├── AI Provider Status Indicator (🟢 Claude Active / 🟡 Gemini Fallback / ⚪ Degraded)          |
|      └── Outlook Mailbox Indicator (🟢 Connected (user@dpik.com.my) / ⚪ Disconnected)             |
|                                                                                                    |
|   3. Livewire AI Copilot Drawer (Cmd+J)                                                            |
|      ├── Header Ribbon: Displays active AI engine & mailbox sync status                            |
|      ├── Preset Ribbon: Dynamically filters email presets based on Outlook auth status            |
|      └── Action Cards: Injects authenticated user token into Graph API outbound requests            |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```

### 2.1 UI Entry Points & Navigation Paths
1. **User Profile Dropdown**: Direct shortcut to **Executive Settings** (`/admin/executive-settings`).
2. **Copilot Drawer Header Badge**:
   - Clicking `Outlook: Disconnected` badge opens a slide-over modal linking directly to the Microsoft configuration tab.
   - Clicking `AI: Active (Claude 3.7)` displays remaining token budget, latency probe, and key configuration link.
3. **Preset Execution Ribbon**:
   - If an executive triggers an email preset (e.g., *"Summarize unread tender emails"*), but Outlook credentials are not configured, Tadbir renders an inline interactive prompt:
     > ⚠️ *Outlook integration is not connected for your account. [Configure Microsoft Graph Credentials in Settings →]*

---

## 3. Configuration Verification & Reflection Checks

To guarantee that any change made in the Settings UI immediately and accurately reflects in runtime operations, the following automated verification checks execute upon saving:

### 3.1 Live Connection Probes (Instant Health Preflight)
When the executive saves their settings or clicks **Test Connection**, the system initiates a hermetic diagnostic probe:

1. **Anthropic Probe**:
   - Executes an ephemeral 1-token query (`messages.create(max_tokens=1, messages=[{"role":"user","content":"ping"}])`).
   - Verifies HTTP 200 response and records round-trip latency.
2. **Google Gemini Probe**:
   - Executes an ephemeral countTokens or minimal generation probe.
   - Verifies API key authorization and quota validity.
3. **Microsoft Graph Probe**:
   - Acquires an OAuth client credentials token against `https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token`.
   - Executes a lightweight `GET https://graph.microsoft.com/v1.0/users/{user_email}/mailboxSettings` or `/mailFolders/Inbox`.
   - Validates that required scopes (`Mail.ReadWrite`, `Mail.Send`, `User.Read`) are granted.

### 3.2 Cache Eviction & State Propagation
- **Settings Store Invalidation**: Immediately calls `app(SettingsContainer::class)->clearCache()` to evict stale cached configurations.
- **Livewire Event Dispatch**: Emits `settings-updated` and `outlook-status-changed` events across all active client sessions.
- **Session Identity Refresh**: Refreshes the authenticated `User` model instance in memory, ensuring subsequent agent turns read updated encrypted casts.

---

## 4. Input Validation Guards & Graceful Error Fallback

Invalid or malformed inputs must never crash the workstation or result in generic "500 Internal Server Error" messages. The system intercepts validation failures, captures exact upstream provider error payloads, and presents clear remediation steps.

```text
+----------------------------------------------------------------------------------------------------+
|                                  GUARD & ERROR DIAGNOSTIC PIPELINE                                 |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|   [User Input Submitted]                                                                           |
|             │                                                                                      |
|             ▼                                                                                      |
|   ┌────────────────────────────────┐                                                               |
|   │ 1. Syntax & Format Guard       │ ──► [Fail] ──► Render Format Guide (e.g. UUID format)          |
|   └────────────────────────────────┘                                                               |
|             │ [Pass]                                                                               |
|             ▼                                                                                      |
|   ┌────────────────────────────────┐                                                               |
|   │ 2. Upstream Provider Probe     │                                                               |
|   └────────────────────────────────┘                                                               |
|             │                                                                                      |
|      ┌──────┴───────────────────────────┐                                                          |
|      ▼ [Success]                        ▼ [Error Intercepted]                                      |
|   [Active 🟢]                    ┌─────────────────────────────────────────────────────────────┐   |
|                                  │ 3. Exact Error Extraction & Remediation Engine              │   |
|                                  │    - Intercept provider error code                          │   |
|                                  │    - Display exact provider message                         │   |
|                                  │    - Provide step-by-step fix instructions                  │   |
|                                  │    - Gracefully fallback to secondary provider/offline mode │   |
|                                  └─────────────────────────────────────────────────────────────┘   |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
```

### 4.1 Input Guard Specifications & Remediation Matrix

| Field | Guard Rule / Pattern | Detected Error Condition | Exact Provider / System Error Output | Remediation Guide & Expected Input Format |
| :--- | :--- | :--- | :--- | :--- |
| **Anthropic Key** | `^sk-ant-api03-[A-Za-z0-9_-]{80,}$` | Invalid key prefix or length | `401 Unauthorized: invalid_x_api_key` | **Fix**: Ensure your key begins with `sk-ant-api03-` copied directly from Anthropic Console (`console.anthropic.com`). Do not include surrounding spaces or quotes. |
| **Anthropic Key** | Upstream Probe | Credit balance exhausted | `400 Bad Request: credit_balance_too_low` | **Fix**: Your Anthropic organization has run out of prepaid credits. Add billing credits in Anthropic Console. |
| **Gemini Key** | `^AIzaSy[A-Za-z0-9_-]{33}$` | Invalid characters or wrong length | `400 Bad Request: API_KEY_INVALID` | **Fix**: Google Gemini keys must start with `AIzaSy` followed by 33 characters. Generate a fresh key at `aistudio.google.com`. |
| **Microsoft Client ID** | UUID v4 Format | Malformed string (e.g., App Name entered instead of ID) | `Client Error: Value must be a valid 36-character UUID` | **Fix**: Provide the **Application (client) ID** from Azure Portal, formatted as `8-4-4-4-12` hex characters (e.g., `12345678-abcd-ef01-2345-6789abcdef01`). |
| **Microsoft Client Secret** | Non-empty string | Secret expired or value copied incorrectly | `401 Unauthorized (AADSTS7000215): Invalid client secret provided or secret has expired.` | **Fix**: In Azure Portal → App Registrations → *Certificates & Secrets*, generate a new Client Secret and copy the **Value** column (not the Secret ID). |
| **Microsoft Tenant ID** | UUID v4 or `common` | Directory not found | `400 Bad Request (AADSTS700016): Application with identifier was not found in the directory.` | **Fix**: Provide the exact **Directory (tenant) ID** from Azure Portal overview. Confirm the App Registration exists within your DPIK tenant. |
| **Microsoft Permissions** | Graph API Scope Check | Missing Mail scopes | `403 Forbidden (ErrorAccessDenied): Access is denied. Check credentials and permissions.` | **Fix**: In Azure Portal → *API permissions*, ensure `Mail.ReadWrite`, `Mail.Send`, and `User.Read` are added and **Grant admin consent** is clicked. |

---

## 5. Graceful Fallback Engine

When an error occurs on any configured integration, Tadbir executes a non-blocking graceful fallback:

1. **AI Gateway Failover**:
   - If the user's configured Anthropic key returns `401`, `429`, or `500`, the AI gateway logs the incident, redacts any PII, displays the exact provider error toast, and **automatically routes the turn to Google Gemini 2.5 Flash** without dropping the user's prompt.
2. **Outlook MCP Degraded Mode**:
   - If Microsoft Graph credentials fail authentication, the AI Copilot continues executing general reasoning, note-taking, task planning, and project register searches.
   - When asked an email-specific question, the copilot responds with:
     > *"I cannot access your Outlook mailbox due to a Microsoft Graph authentication error (`AADSTS7000215: Invalid client secret`). All other project and workspace features remain active. You can update your secret in [Executive Settings](/admin/executive-settings)."*
3. **Zero Plaintext Error Leakage**:
   - All raw error messages from upstream providers are sanitized to redact authorization headers, bearer tokens, and sensitive email addresses before rendering in UI notifications.

---

## 6. Implementation Checklist & Verification Gates

- [ ] Add `microsoft_client_id`, `microsoft_client_secret` (encrypted), `microsoft_tenant_id` to `users` table migration.
- [ ] Implement live diagnostic probe actions in `ExecutiveSettings.php` (`testAiConnection()`, `testOutlookConnection()`).
- [ ] Build interactive error alert cards in `executive-settings.blade.php` displaying exact provider responses with remediation links.
- [ ] Connect `OutlookMcpBridge` to resolve credentials dynamically from the authenticated user with system fallback.
- [ ] Write unit and feature Pest tests validating:
  - Format validation rules for UUIDs and API key prefixes.
  - Interception of mock 401/403/AADSTS provider errors.
  - Graceful fallback execution when user keys are missing or invalid.
