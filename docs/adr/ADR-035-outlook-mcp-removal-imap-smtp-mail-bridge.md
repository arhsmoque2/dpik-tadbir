# ADR-035: Remove Outlook MCP (Microsoft Graph), Adopt IMAP/SMTP MailBridge

- **Status**: Accepted
- **Date**: 2026-09-02
- **Author**: Claude (session), on the operator's direction
- **Supersedes**: ADR-001 §"Adopt `outlook-mcp` for Graph API Operations" and ADR-003 in full. ADR-013 and ADR-017's mentions of the Outlook MCP bridge should be read as referring to `MailBridge` below.
- **Related**: Issue #40 ("Outlook MCP bridge fails on deployed Cloud Run: `uv` binary not present in the image")

## 1. Problem Statement

Issue #40 tracked a production failure: every Outlook-related AI Copilot action failed with `sh: 1: exec: uv: not found`, because the production Cloud Run image never installed the `uv` binary the Outlook MCP bridge (`OutlookMcpBridge`, now removed) shelled out to.

Investigating the fix surfaced a deeper problem than a missing binary. The bridge's whole premise — `uv run python -m outlook_mcp.server` — depended on a Python package, `outlook_mcp`, that **does not exist anywhere reachable**:

- It is not vendored in this repository.
- It is not published under the `arhsmoque2` GitHub org (checked: no `outlook-mcp`/`outlook_mcp` repo exists there).
- The only public PyPI package with a matching name (`outlook-mcp` by a third-party author) implements a different tool surface (`get-emails`, `send-email`, …) than what this codebase's tools actually call (`outlook_list_inbox_delta`, `outlook_search_mail`, `outlook_read_message` with `concise=True`, `outlook_create_draft`, `outlook_reply`, `outlook_forward`, `outlook_auth_status`) — it is not a drop-in.
- ADR-001/ADR-003 themselves describe the intended package as storing tokens in the **Windows Credential Store** with **OS-keyring** authentication — a design for a process running on an individual's own desktop, fundamentally incompatible with a stateless, multi-tenant Linux container.

In short: adding `uv` to the Dockerfile (issue #40's literal proposed fix) would not have restored any functionality — there was nothing installable behind it to run. The feature was scaffolding without an implementation.

Separately, the operator's own assessment: registering an Azure Entra application (Client ID/Secret/Tenant ID) per executive is unnecessary friction. Every DPIK executive mailbox already lives on the same company mail server (`mail.dpik.com.my`) with the same IMAP/SMTP settings, differing only by the executive's own email address and password — exactly the shape `MailDiagnosticService::probeImap()`/`probeSmtp()` and the `users.imap_*`/`smtp_*` columns (added in an earlier, already-merged migration) were built for, but never wired to anything that actually sent or fetched mail.

## 2. Decision

Remove the Outlook MCP (Microsoft Graph via Python subprocess) mechanism entirely, and implement the company mailbox bridge for real, over IMAP (retrieval) and SMTP (draft/reply/forward), authenticated per-executive with their own mailbox credentials.

**Removed:**
- `App\Services\Mcp\OutlookMcpBridge` (the subprocess/Graph client) and `App\Settings\OutlookSettings` (its unused, never-migrated Spatie settings group).
- `users.microsoft_client_id` / `microsoft_client_secret` / `microsoft_tenant_id` (migration `2026_09_02_000002_drop_microsoft_graph_credentials_from_users_table`).
- `services.outlook_mcp` config, `MICROSOFT_CLIENT_ID`/`_SECRET`/`_TENANT_ID` and `OUTLOOK_MCP_COMMAND`/`_ARGS`/`_TIMEOUT` env vars, and the Entra app registration UI in Executive Settings.

**Added:**
- `App\Services\Mail\MailBridge` (`app/Services/Mail/MailBridge.php`) — the same public surface the six `App\Mcp\Tools\Outlook\*` tool classes already called (`fetchInboxDelta`, `searchMail`, `readMessage`, `createDraft`, `sendReply`, `forwardMessage`, `checkAuthStatus`), reimplemented over:
  - **Retrieval** — PHP's `ext-imap`, connecting to `users.imap_host`/`imap_port` with `users.imap_username`/`imap_password` (falls back to the config default host `mail.dpik.com.my` and the user's own email as username).
  - **Outbound** (draft/reply/forward) — Symfony Mailer's SMTP transport, built per-request from `users.smtp_host`/`smtp_port`/`smtp_password` (falls back to the IMAP password, matching "same password, different email" per-mailbox). Drafts are staged by `imap_append`-ing directly to the mailbox's `Drafts` IMAP folder — the mailbox-native equivalent of a Graph "create draft" call.
  - Testing-environment mock responses are preserved verbatim from the old bridge, so tool-level tests didn't need to change shape.
- `services.company_mail` config (`COMPANY_MAIL_HOST`/`_IMAP_PORT`/`_SMTP_PORT`/`_TIMEOUT`/`_DRAFTS_FOLDER`), replacing `services.outlook_mcp`.
- `imap` PHP extension in the production `Dockerfile`, CI (`shivammathur/setup-php`'s `extensions:`), and the devcontainer image.
- Migration `2026_09_02_000002_drop_microsoft_graph_credentials_from_users_table`.

**Not changed:** the six `App\Mcp\Tools\Outlook\*` tool classes, their tool names (`outlook_*`), and `App\Mcp\Tools\Concerns\ScopesMailBridge` (renamed from `ScopesOutlookBridge`, same scoping behavior) — the AI-facing tool surface is unchanged, only what's behind it. The "Outlook" naming on the tools describes the executive's mailbox, which is still commonly accessed elsewhere via an Outlook desktop/web client — the tools no longer imply Microsoft Graph specifically.

## 3. Consequences

- **Positive**: Outlook actions in the AI Copilot can actually work in production for the first time — there was never a working implementation behind them before this change, regardless of the `uv` binary.
- **Positive**: Zero Azure Entra app registration required. Onboarding a new executive is "enter your email + mailbox password in Executive Settings", matching how every other DPIK mailbox is already provisioned.
- **Positive**: Removes a class of failure (missing Python runtime, missing/wrong pip package, OS-keyring token store incompatible with a container) that could never have been fully closed without the fixes this ADR makes anyway.
- **Trade-off**: IMAP/SMTP is a lower-fidelity API than Microsoft Graph — no delta sync tokens (a `SINCE <date>` search substitutes, at day granularity, for `lookback_hours`), no rich attachment/HTML MIME handling in this first pass (plain-text bodies only), and per-request SMTP connections rather than a persistent Graph session. Acceptable for the executive-triage use case (concise mode, short bodies, occasional reply/forward), revisit if attachment handling becomes a real requirement.
- **Trade-off**: `ext-imap` is now a hard runtime dependency of the production image, CI, and any local dev environment running real (non-mocked) mail flows.
- **Housekeeping**: ADR-001's "Adopt `outlook-mcp` for Graph API Operations" and ADR-003 in full are superseded by this decision; they are left in place as history rather than rewritten, per this repo's ADR convention (see ADR-034's own precedent).
