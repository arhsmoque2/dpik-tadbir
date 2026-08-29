# ADR-003: Outlook MCP Email Processor & Zero Raw Email Storage Boundary

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
DPIK Tadbir acts as an executive command center, not a replacement for Microsoft Outlook. Storing duplicate raw emails (.eml, bodies, heavy attachments) in local databases introduces storage bloat, synchronization fragility, and compliance liabilities. The Managing Director needs an on-demand AI processor that queries Outlook directly via `outlook-mcp` (Microsoft Graph API), extracts context, and stores exclusively **processed intelligence** (summaries, action items, notes, and project register updates).

## Decision
1. **Adopt On-Demand Outlook MCP Processor**:
   - The application does not maintain local raw email tables (`raw_emails`, `email_bodies`, `email_attachments`).
   - The AI assistant connects to the local `outlook-mcp` Python server (using OS Keyring authentication) to invoke typed tools (`outlook_list_inbox_delta`, `outlook_search_mail`, `outlook_read_message`).
2. **Concise Token-Saving Triage Mode**:
   - Read tools default to `concise=True`, stripping extraneous MIME headers, tracking pixels, and CSS stylesheets, reducing prompt token consumption by ~10×.
3. **Expose Outlook Engine Configurability in Settings**:
   - **Bridge Connection**: Configurable launcher path / HTTP port (default: `uv run --directory ../outlook-mcp outlook-mcp`).
   - **Default Scan Fetch Limit**: Configurable message ceiling per query (default: `25`, range `10–100`).
   - **Lookback Window**: Default delta lookback timeframe (default: `24 hours`, options `12h`, `24h`, `48h`, `7d`).
   - **Folder Inclusions & Exclusions**: Editable blacklist/whitelist (default excludes: `["Junk", "Deleted Items", "Archive"]`).
   - **Priority Sender Domains**: Whitelist of critical client/authority domains (e.g. `["jkr.gov.my", "jps.gov.my"]`) prioritized in morning scans.

## Consequences
- **Positive**: Zero database bloat; eliminates continuous background IMAP sync failures.
- **Positive**: Clean separation between standard email client usage (Outlook) and executive synthesis (Tadbir).
- **Trade-off**: Requires host OS credentials initialized once via `outlook-mcp auth`.
