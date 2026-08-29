# DPIK Tadbir: Intent

## [INTENT-01] Purpose & Mission
DPIK Tadbir is an AI-assisted executive management command center built for senior company leadership (the Managing Director, partners, and designated executives) to oversee multi-project health and act as an **intelligent email processing and synthesis layer** over their existing Outlook accounts. It connects directly to each user's Outlook mailbox via `outlook-mcp` (Graph API) to let the AI search, read, draft, reply, and forward on-demand, while storing only the **processed AI outputs** (summaries, commitments, notes, tasks, and project register intelligence)—never replicating raw email storage.

## [INTENT-02] Primary Problem Space
1. **Separation of Communication vs. Executive Memory**: Senior leaders use standard Outlook for day-to-day email. However, Outlook lacks structured memory to link email commitments to project registers, personal tasks, and permanent company records.
2. **Context Loss & Executive Burden**: Manually scanning hundreds of Outlook threads wastes executive time. Leaders need the AI to check, search, and synthesize emails on-demand via presets (*"What's new today?"*, *"Check my email today"*).
3. **Bloat of Raw Email Ingestion**: Storing duplicate raw email blobs creates database bloat, sync friction, and compliance overhead. Storing only processed intelligence (summaries, action items, receipts) keeps the database lightweight and high-signal.
4. **Action Memory & Traceability**: Executives need an immutable ledger of all actions taken (drafts, replies, forwards, notes created) to provide clear daily and weekly audit rollups.
5. **Human-in-the-Loop Safety**: When the AI formulates drafts, replies, or forwards via Outlook, it must require explicit human confirmation before dispatching.
6. **Controlled Registration & Private Sovereignty**: Public signup is insecure, while hardcoding a single fixed user limits team collaboration. The platform needs strict email-whitelisted registration where each whitelisted executive receives an isolated private workstation.

## [INTENT-03] Core Operating Principles
1. **Lightweight AI Processor (Zero Raw Email Storage)**: The app does not clone an email client. It queries Outlook on-demand via `outlook-mcp` (Graph API), extracts context, and persists only the high-value processed outputs (summaries, personal notes, tasks, project register updates).
2. **Executive Preset Inquiries**: One-click quick-action chips (*"What's new today?"*, *"Check my email today"*, *"Action items needing reply"*) instruct the AI to scan Outlook delta changes and produce immediate, structured executive digests.
3. **Project Register as Compounding Company Intelligence**: Every time an executive reviews or summarizes Outlook threads for a project, the extracted insights and commitments are indexed under that project's register entry, building permanent shared company familiarity across all authorized leaders.
4. **Explicit Write Confirmation**: The AI can draft, reply, and forward via Outlook, but every single outbound action requires explicit operator approval on an interactive action card.
5. **Action Memory & Rolling Audit Summaries**: Every action executed by the AI is logged into an immutable activity ledger, enabling the AI to recall past decisions and auto-generating daily/weekly executive activity rollups.
6. **Whitelisted Multi-Executive Registration & Workspace Isolation**: Access is restricted strictly to pre-approved, whitelisted email addresses ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)). Each registered executive receives their own private Outlook mailbox credentials, chat sessions, personal notes, and presets, with zero inter-user data leakage.

## [INTENT-04] Anti-Goals (What This Product Is NOT)
1. **Not an Open Public SaaS**: Registration is strictly closed and permitted only for whitelisted corporate email addresses.
2. **Not a Duplicate Email Client**: It does not replace Outlook and does not store raw email inboxes, message bodies, or heavy attachment blobs.
3. **Not an Autonomous Outbound Bot**: It will never dispatch an email, reply, or forward without explicit human confirmation.
4. **Not a Generic Memoryless Chatbot**: It maintains permanent project register memory and action audit logs across sessions.
5. **Not a Multi-User Ticketing Hierarchy (Active Phase)**: Full employee ticketing, department hierarchies, and workload balancing are deferred ([`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)); the active system delivers sovereign executive AI command centers.
