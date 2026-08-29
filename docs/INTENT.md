# DPIK Tadbir: Intent

## [INTENT-01] Purpose & Mission
DPIK Tadbir is an AI-assisted executive management command center built for the Managing Director (MD) to oversee multi-project health, monitor staff capacity, and act as an **intelligent email processing and synthesis layer** over the MD's existing Outlook account. It connects directly to Outlook via `outlook-mcp` (Graph API) to let the AI search, read, draft, reply, and forward on-demand, while storing only the **processed AI outputs** (summaries, commitments, notes, tasks, and project register intelligence)—never replicating raw email storage.

## [INTENT-02] Primary Problem Space
1. **Separation of Communication vs. Executive Memory**: The MD uses standard Outlook for day-to-day email. However, Outlook lacks structured memory to link email commitments to project registers, staff tasks, and permanent company records.
2. **Context Loss & Executive Burden**: Manually scanning hundreds of Outlook threads wastes executive time. The MD needs the AI to check, search, and synthesize emails on-demand via presets (*"What's new today?"*, *"Check my email today"*).
3. **Bloat of Raw Email Ingestion**: Storing duplicate raw email blobs creates database bloat, sync friction, and compliance overhead. Storing only processed intelligence (summaries, action items, receipts) keeps the database lightweight and high-signal.
4. **Action Memory & Traceability**: The MD needs an immutable ledger of all actions taken (drafts, replies, forwards, notes created) to provide a clear daily and weekly audit summary.
5. **Human-in-the-Loop Safety**: When the AI formulates drafts, replies, or forwards via Outlook, it must require explicit human confirmation before dispatching.

## [INTENT-03] Core Operating Principles
1. **Lightweight AI Processor (Zero Raw Email Storage)**: The app does not clone an email client. It queries Outlook on-demand via `outlook-mcp` (Graph API), extracts context, and persists only the high-value processed outputs (summaries, personal notes, tasks, project register updates).
2. **Executive Preset Inquiries**: One-click quick-action chips (*"What's new today?"*, *"Check my email today"*, *"Action items needing reply"*) instruct the AI to scan Outlook delta changes and produce immediate, structured executive digests.
3. **Project Register as Compounding Intelligence**: Every time the AI reviews or summarizes Outlook threads for a project, the extracted insights and commitments are indexed under that project's register entry, building permanent contextual familiarity.
4. **Explicit Write Confirmation**: The AI can draft, reply, and forward via Outlook, but every single outbound action requires explicit operator approval on an interactive action card.
5. **Action Memory & Rolling Audit Summaries**: Every action executed by the AI is logged into an immutable activity ledger, enabling the AI to recall past decisions and auto-generating daily/weekly executive activity rollups.

## [INTENT-04] Anti-Goals (What This Product Is NOT)
1. **Not a Duplicate Email Client**: It does not replace Outlook and does not store raw email inboxes, message bodies, or heavy attachment blobs.
2. **Not an Autonomous Outbound Bot**: It will never dispatch an email, reply, or forward without explicit human confirmation.
3. **Not a Generic Memoryless Chatbot**: It maintains permanent project register memory and action audit logs across sessions.
