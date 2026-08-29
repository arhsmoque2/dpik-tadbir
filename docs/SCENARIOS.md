# DPIK Tadbir: User Scenarios

## [SCEN-01] On-Demand Email Check via "What's New Today?" Preset
1. **Entry**: MD opens the DPIK Tadbir dashboard.
2. **Progress**: MD clicks the preset button: **[What's new today?]** (or **[Check my email today]**).
3. **Execution**:
   - The AI calls `outlook-mcp` (`OutlookListInboxDeltaTool` with `concise=True`) to query the MD's Outlook inbox directly via Microsoft Graph API.
   - The AI reviews and parses the returned email threads, groups topics by client and urgency, and filters out non-critical noise.
4. **Exit**: AI outputs a concise executive summary directly in the chat drawer highlighting 3 urgent client inquiries and 2 pending approvals. No raw emails are saved locally—only the generated summary and action cards are stored.

## [SCEN-02] Supervised Email Reply & Forward via Outlook
1. **Entry**: Reviewing the morning briefing, the MD wants to respond to a tender query from Client X.
2. **Progress**: MD prompts: *"Draft a reply to Client X confirming the updated hydraulic report will be sent by Thursday 3 PM, and forward the drawing attachments to Engineer A for review."*
3. **Execution**:
   - AI generates two staged **Action Cards** in the chat via `ProposeActionCardTool`:
     1. **Reply Action Card**: `To: Client X`, `Subject`, `Body preview`.
     2. **Forward Action Card**: `To: Engineer A`, `Note preview`, `Attached files`.
   - MD reviews the drafts and clicks **[Approve & Dispatch]**.
   - AI invokes `OutlookReplyTool` and `OutlookForwardTool` through `OutlookMcpBridge`.
   - The system records the execution into `ai_action_receipts`.
4. **Exit**: The emails are dispatched from the MD's actual Outlook account, an immutable audit receipt is logged, and the AI acknowledges completion.

## [SCEN-03] Project Register Categorization & Knowledge Accumulation
1. **Entry**: MD prompts the AI: *"Search Outlook for all emails regarding Sungai Udang Barrage and extract our latest milestone commitments into the Project Register."*
2. **Progress**:
   - AI executes `OutlookSearchMailTool` with query `"Sungai Udang"` (`concise=True`).
   - AI extracts agreed deadlines, revision submissions, and site survey milestones.
   - AI presents the extracted summary: *"Save findings to Project Register under 'PC-2023-011: Sungai Udang'?"*
   - MD clicks **[Confirm & Save]**.
3. **Execution**: The AI saves the structured summary into `project_registry_entries` via `CommitProjectRegisterTool` (storing commitments, dates, and client contacts).
4. **Exit**: The project register is enriched. Next week, when the MD asks *"What commitments did we make on Sungai Udang?"*, the AI immediately draws from the accumulated project register memory via `QueryProjectRegisterTool`.

## [SCEN-04] Daily & Weekly Executive Activity Rollup
1. **Entry**: MD asks *"What actions did the AI complete this week?"* (or clicks **[Weekly Activity Rollup]**).
2. **Progress**:
   - AI queries the `ai_action_receipts` table for all confirmed actions across the week via `ActionMemoryService`.
   - AI groups actions by date and project: drafts sent, replies dispatched, summaries generated, notes created, and tasks registered.
3. **Exit**: AI renders an executive markdown report providing full transparency into all completed activities and historical decisions.

## [SCEN-05] Project & Staff Workload Rebalancing & Ticket Delegation
- **Status**: `Deferred (Keep In View / KIV)` — *See [`ADR-012`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/docs/adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)*.
1. **Entry**: MD notices a deadline warning for the Sungai Udang tender in the **Project Health Board** widget.
2. **Progress**: MD prompts: *"Who is assigned to the Sungai Udang hydraulic calculation ticket, and what is their current workload?"*
3. **Execution**:
   - AI invokes `GetStaffWorkloadTool` and queries `StaffWorkloadService` to assess active tickets for Engineer B.
   - AI responds: *"Engineer B currently holds 7 open tickets (1.40 nominal capacity index), causing the Sungai Udang calculation to bottleneck. Engineer C in the same department has 2 active tickets."*
   - MD prompts: *"Reassign the calculation ticket to Engineer C with note 'Prioritized by MD for Thursday submission'."*
   - AI presents a `ReassignTicketActionCard` for MD approval.
   - MD clicks **[Confirm Reassignment]**.
   - AI invokes `ReassignTicketTool`, updates the `Ticket` record, and logs an `AiActionReceipt`.
4. **Exit**: Workload is rebalanced, the bottleneck is cleared, and an audit trail of the reassignment is permanently recorded upon Phase 2 resumption.

## [SCEN-06] Multi-Role Access Control & Sovereign Privacy Boundary
- **Status**: `Deferred (Keep In View / KIV)` for internal multi-user tiers — *See [`ADR-012`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/docs/adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)*.
- In the active build, DPIK Tadbir operates as a single-user personal command center (`super_admin`), while external coding agents connecting via `/mcp` authenticate with scoped bearer tokens.
- *Preserved Phase 2 Spec*: When multi-tenant roles are activated, `PersonalNotePolicy` and `PersonalTaskPolicy` strictly scope queries to `auth()->id()`, ensuring complete tenant privacy and role segregation across all roles (`super_admin`, `managing_director`, `admin`, `project_manager`, `staff`, `hr`).
