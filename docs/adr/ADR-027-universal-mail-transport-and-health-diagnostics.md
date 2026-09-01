# ADR-027: Universal Mail Transport Architecture (Native Exabytes IMAP/SMTP Primary & Microsoft Graph Secondary)

**Status**: Accepted  
**Date**: 2026-09-01  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
DPIK's primary corporate email infrastructure is hosted on Exabytes (`mail.dpik.com.my`) using standard IMAP/SMTP protocols rather than Microsoft 365 Exchange. Microsoft's recent policy changes deprecated creating standalone App Registrations for personal accounts without an Entra ID directory, creating friction for Microsoft Graph API onboarding. To deliver immediate email intelligence to company executives without Azure dependencies, the system requires a native IMAP/SMTP bridge while retaining Microsoft Graph compatibility.

## Decision
1. **Universal Mail Transport Strategy**:
   - **Primary Driver**: Native IMAP SSL (port 993) and SMTP SSL (port 465) connecting directly to `mail.dpik.com.my`.
   - **Secondary Driver**: Microsoft Graph API MCP bridge retained for future Microsoft 365 migrations.
2. **Encrypted Mailbox Credentials Schema**:
   - Migration `2026_09_01_000007_add_imap_credentials_to_users_table.php` adds `imap_host`, `imap_port`, `imap_username`, `imap_password`, `smtp_host`, `smtp_port`, `smtp_password` to `users` table.
   - `imap_password` and `smtp_password` are encrypted using AES-256 via Laravel Eloquent `'encrypted'` casts.
   - Defaults automatically map to `mail.dpik.com.my` with the user's logged-in email.
3. **Live Socket Diagnostic Suite (`MailDiagnosticService`)**:
   - `probeImap()`: Opens an SSL socket connection to port 993, verifies the `* OK` banner, measures latency in ms, and tests IMAP authentication.
   - `probeSmtp()`: Opens an SSL socket connection to port 465, validates the `220` SMTP banner, exchanges `EHLO`, and measures response latency.
4. **At-A-Glance System Health Bar in Executive Settings**:
   - Added a 5-service health status header displaying live connectivity for:
     1. Direct AI Providers (Anthropic/Gemini)
     2. OpenRouter Gateway
     3. DPIK IMAP Incoming Mailbox (Port 993)
     4. DPIK SMTP Outgoing Server (Port 465)
     5. Microsoft Graph / Outlook (Optional)
   - Features a **"⚡ Run Full System Health Check"** button that executes all diagnostic probes simultaneously.

## Consequences
- **Positive**: Zero Azure / Microsoft setup required for company staff. Instant mailbox access on `mail.dpik.com.my`.
- **Positive**: Full executive privacy — mailbox isolation is enforced per user.
- **Positive**: Live at-a-glance transparency into all system dependencies with millisecond latency metrics.
