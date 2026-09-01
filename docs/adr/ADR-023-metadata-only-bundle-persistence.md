# ADR-023: Metadata-Only Bundle Persistence & Live Graph Body Fetching

- **Status**: Accepted
- **Date**: 2026-09-01
- **Deciders**: Ir. Abdul Rahman Hilmi, AGY Antigravity
- **Consulted**: `INTENT.md`, `DESIGN-02`, `ADR-022`, `CAP-019`

---

## 1. Context & Problem Statement

`ADR-022` introduced **Materialized Bundles** as the core human-first unit of email retrieval. However, `INTENT.md` and `DESIGN-02` establish a sovereign directive: **Zero raw email storage on local infrastructure**.

If email retrievals stored full email bodies and HTML attachments in the local database (`bundles` / `bundle_emails`), it would violate `DESIGN-02` sovereign isolation principles and create data compliance overhead.

We need a clear architectural policy defining what is stored in `bundles` and `bundle_emails` vs what is retrieved live on demand.

---

## 2. Decision Outcome

We adopt **Option 1: Metadata-Only Persistence**.

### Key Rules:
1. **Database Persistence (`bundle_emails`)**:
   - Store only lightweight pointer metadata: `message_id`, `from_name`, `from_email`, `subject`, `snippet` (preview snippet up to 255 chars), and `received_at`.
   - **Zero raw email body text**, zero HTML bodies, and **zero attachments** are persisted in PostgreSQL/SQLite.

2. **Live On-Demand Reading**:
   - When an executive expands an email to read the full body in `BundleResource`, the application invokes `OutlookReadMessageTool` live against the Microsoft Graph API using `message_id`.
   - The body is rendered ephemerally in the browser window and never saved to the local database disk.

3. **Zero-AI Executive Notes**:
   - Executive annotations written directly on a Bundle are saved to `$bundle->notes`.
   - Note saving is 100% human-driven with zero LLM/token cost.

---

## 3. Consequences

### Positive
- **100% Alignment with `DESIGN-02`**: Local database remains lightweight, sovereign, and compliant.
- **Low Database Footprint**: Thousands of Bundles can be indexed with negligible storage overhead.
- **Zero Privacy Risk**: Loss or compromise of local database backups exposes zero email body text or confidential attachments.

### Negative
- **Requires Network Access for Full Reading**: Reading full text requires an active Graph API token / network connection (snippets remain available offline).
