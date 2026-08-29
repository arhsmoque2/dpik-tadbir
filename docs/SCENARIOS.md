# DPIK Tadbir: User Scenarios

## [SCEN-01] Executive Morning Briefing
1. **Entry**: MD opens DPIK Tadbir dashboard on desktop or mobile.
2. **Progress**: MD clicks the AI Assistant panel or issues the query *"Give me my morning briefing"*.
3. **Execution**: The AI invokes `outlook_changes_since` / `outlook_list_inbox_delta` to get new unread high-priority emails, checks pending tickets across active projects in `dpik-tadbir`, and retrieves today's due `PersonalTasks`.
4. **Exit**: AI outputs a concise executive summary formatted with bullet points, flagging 2 urgent client emails and 1 overdue project deliverable.

## [SCEN-02] Inbox Triage, Summarization, and Note Capture
1. **Entry**: MD notices an important thread regarding "Site Survey Phase 2".
2. **Progress**: MD prompts the AI: *"Summarize the thread from Client X and save the key commitments to my personal notes."*
3. **Execution**:
   - AI calls `outlook_read_message` / `outlook_list_thread` with `concise=True`.
   - AI extracts the key decisions and deliverables.
   - AI calls `PersonalNoteTool::create` to persist a new `PersonalNote` with tags `["Client X", "Site Survey"]` and backlinks to the Outlook thread ID.
4. **Exit**: AI displays the formatted summary in chat, confirms the note has been saved to the database, and provides a direct link to view the Note in the Filament Notes panel.

## [SCEN-03] Supervised Email Reply
1. **Entry**: MD wants to reply to a pending vendor query.
2. **Progress**: MD prompts: *"Draft a polite reply to Vendor Y agreeing to the revised schedule for Friday, but requesting updated drawings by Wednesday 5 PM."*
3. **Execution**:
   - AI formats the reply and generates an in-chat draft preview showing `To`, `Subject`, and `Body`.
   - AI provides an interactive **[Approve & Send]** action button.
   - MD reviews the draft, makes a minor tweak, and clicks **[Approve & Send]**.
   - AI invokes `outlook_send_message` via MCP and confirms dispatch.
4. **Exit**: The email is sent from the MD's authenticated Outlook account, logged in the audit trail, and reflected in the visual Inbox.

## [SCEN-04] Project Bottleneck Diagnosis & Workload Rebalancing
1. **Entry**: MD notices the "JPS Kelantan" project timeline is slipping.
2. **Progress**: MD asks: *"Why is JPS Kelantan delayed, and who is currently assigned to the open tickets?"*
3. **Execution**:
   - AI queries `ProjectOversightTool`, retrieving tickets for project `JPS Kelantan` and current capacity across `PositionAssignments`.
   - AI identifies that Senior Engineer A has 14 open tickets while Junior Engineer B has capacity.
   - AI proposes: *"Reassign 4 CAD drafting tickets (#102, #104, #108, #112) from Engineer A to Engineer B."*
   - MD clicks **[Execute Rebalance]**.
4. **Exit**: Tickets are updated in the database, notifications are queued, and an audit entry is created.
