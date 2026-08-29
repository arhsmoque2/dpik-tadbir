# DPIK Tadbir: User Scenarios

## [SCEN-01] On-Demand Email Check via "What's New Today?" Preset
1. **Entry**: MD opens the DPIK Tadbir dashboard.
2. **Progress**: MD clicks the preset button: **[What's new today?]** (or **[Check my email today]**).
3. **Execution**:
   - The AI calls `outlook-mcp` (`outlook_list_inbox_delta` / `outlook_changes_since` with `concise=True`) to query the MD's Outlook inbox directly via Microsoft Graph API.
   - The AI reviews and parses the returned email threads, groups topics by client and urgency, and filters out non-critical noise.
4. **Exit**: AI outputs a concise executive summary directly in the chat drawer highlighting 3 urgent client inquiries and 2 pending approvals. No raw emails are saved locally—only the generated summary and action cards are stored.

## [SCEN-02] Supervised Email Reply & Forward via Outlook
1. **Entry**: Reviewing the morning briefing, the MD wants to respond to a tender query from Client X.
2. **Progress**: MD prompts: *"Draft a reply to Client X confirming the updated hydraulic report will be sent by Thursday 3 PM, and forward the drawing attachments to Engineer A for review."*
3. **Execution**:
   - AI generates two staged **Action Cards** in the chat:
     1. **Reply Action Card**: `To: Client X`, `Subject`, `Body preview`.
     2. **Forward Action Card**: `To: Engineer A`, `Note preview`, `Attached files`.
   - MD reviews the drafts and clicks **[Approve & Dispatch]**.
   - AI invokes `outlook_reply` and `outlook_forward` through `outlook-mcp`.
   - The system records the execution into `ai_action_receipts`.
4. **Exit**: The emails are dispatched from the MD's actual Outlook account, an immutable audit receipt is logged, and the AI acknowledges completion.

## [SCEN-03] Project Register Categorization & Knowledge Accumulation
1. **Entry**: MD prompts the AI: *"Search Outlook for all emails regarding Sungai Udang Barrage and extract our latest milestone commitments into the Project Register."*
2. **Progress**:
   - AI executes `outlook_search_mail` with query `"Sungai Udang"` (`concise=True`).
   - AI extracts agreed deadlines, revision submissions, and site survey milestones.
   - AI presents the extracted summary: *"Save findings to Project Register under 'PC-2023-011: Sungai Udang'?"*
   - MD clicks **[Confirm & Save]**.
3. **Execution**: The AI saves the structured summary into `project_registry_entries` (storing commitments, dates, and client contacts).
4. **Exit**: The project register is enriched. Next week, when the MD asks *"What commitments did we make on Sungai Udang?"*, the AI immediately draws from the accumulated project register memory.

## [SCEN-04] Daily & Weekly Executive Activity Rollup
1. **Entry**: MD asks *"What actions did the AI complete this week?"* (or clicks **[Weekly Activity Rollup]**).
2. **Progress**:
   - AI queries the `ai_action_receipts` table for all confirmed actions across the week.
   - AI groups actions by date and project: drafts sent, replies dispatched, summaries generated, and notes created.
3. **Exit**: AI renders an executive markdown report providing full transparency into all completed activities and historical decisions.
