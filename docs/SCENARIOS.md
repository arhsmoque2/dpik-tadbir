# DPIK Tadbir: User Scenarios

## [SCEN-01] On-Demand Email Check via "What's New Today?" Preset
1. **Entry**: Executive opens the DPIK Tadbir dashboard (a Bundle/Session list, per [`CAP-009`](CAPABILITIES.md#cap-009-executive-ai-command-center--visual-panel-filament-v4)).
2. **Progress**: Executive taps **Retrieve** with no custom filter set, so the default filter applies (mail sent today, addressed directly — `To:`, not `CC:` — new since the last check only, per [`CAP-019`](CAPABILITIES.md#cap-019-bundle-based-retrieval-default-filter--auto-promotion-engine)).
3. **Execution**:
   - The system calls `outlook-mcp` (`OutlookListInboxDeltaTool` with `concise=True`) to query the executive's individual Outlook inbox directly via Microsoft Graph API using their private session credentials.
   - The matched emails are saved as a new **Bundle** (e.g. `Bundle 1`), a durable list the executive can open and read directly.
4. **Exit — two valid paths, per [`INTENT-03`](INTENT.md#intent-03-core-operating-principles) item 7**:
   - **Manual**: the executive opens Bundle 1, reads the retrieved emails, and writes their own notes on the Bundle. No AI is invoked.
   - **AI-assisted (optional)**: the executive taps "Ask Copilot about this Bundle" and requests a summary; the AI outputs a concise executive summary highlighting urgent client inquiries and pending approvals. No raw emails are saved outside the Bundle — only the generated summary and any resulting action cards are additionally stored.

## [SCEN-02] Supervised Email Reply & Forward via Outlook
1. **Entry**: Reviewing the morning briefing, the executive wants to respond to a tender query from Client X.
2. **Progress**: Executive prompts: *"Draft a reply to Client X confirming the updated hydraulic report will be sent by Thursday 3 PM, and forward the drawing attachments to Engineer A for review."*
3. **Execution**:
   - AI generates two staged **Action Cards** in the chat via `ProposeActionCardTool`:
     1. **Reply Action Card**: `To: Client X`, `Subject`, `Body preview`.
     2. **Forward Action Card**: `To: Engineer A`, `Note preview`, `Attached files`.
   - Executive reviews the drafts and clicks **[Approve & Dispatch]**.
   - AI invokes `OutlookReplyTool` and `OutlookForwardTool` through `OutlookMcpBridge`.
   - The system records the execution into `ai_action_receipts`.
4. **Exit**: The emails are dispatched from the executive's actual Outlook account, an immutable audit receipt is logged, and the AI acknowledges completion.

## [SCEN-03] Project Register Categorization & Knowledge Accumulation
1. **Entry**: Executive prompts the AI: *"Search Outlook for all emails regarding Sungai Udang Barrage and extract our latest milestone commitments into the Project Register."*
2. **Progress**:
   - AI executes `OutlookSearchMailTool` with query `"Sungai Udang"` (`concise=True`).
   - AI extracts agreed deadlines, revision submissions, and site survey milestones.
   - AI presents the extracted summary: *"Save findings to Project Register under 'PC-2023-011: Sungai Udang'?"*
   - Executive clicks **[Confirm & Save]**.
3. **Execution**: The AI saves the structured summary into `project_registry_entries` via `CommitProjectRegisterTool` (storing commitments, dates, client contacts, and `recorded_by_user_id`).
4. **Exit**: The project register is enriched. Next week, when any authorized executive asks *"What commitments did we make on Sungai Udang?"*, the AI immediately draws from the accumulated project register memory via `QueryProjectRegisterTool`.

## [SCEN-04] Daily & Weekly Executive Activity Rollup
1. **Entry**: Executive asks *"What actions did the AI complete this week?"* (or clicks **[Weekly Activity Rollup]**).
2. **Progress**:
   - AI queries the `ai_action_receipts` table for all confirmed actions across the week for `auth()->id()` via `ActionMemoryService`.
   - AI groups actions by date and project: drafts sent, replies dispatched, summaries generated, notes created, and tasks registered.
3. **Exit**: AI renders an executive markdown report providing full transparency into all completed activities and historical decisions for that executive.

## [SCEN-05] Project & Staff Workload Rebalancing & Ticket Delegation
- **Status**: `Deferred (Keep In View / KIV)` — *See [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)*.
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
- **Status**: `Deferred (Keep In View / KIV)` for internal multi-user tiers — *See [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)*.
- In the active build, DPIK Tadbir operates with whitelisted executive accounts (`super_admin`), while external coding agents connecting via `/mcp` authenticate with scoped bearer tokens.
- *Preserved Phase 2 Spec*: When multi-tier roles are activated, `PersonalNotePolicy` and `PersonalTaskPolicy` strictly scope queries to `auth()->id()`, ensuring complete tenant privacy and role segregation across all roles (`super_admin`, `managing_director`, `admin`, `project_manager`, `staff`, `hr`).

## [SCEN-07] Whitelisted Executive Registration & Private Workspace Provisioning
1. **Entry**: Operator adds a senior partner's email (`partner@dpik.com.my`) to the registration whitelist via command line or admin settings.
2. **Progress**:
   - The partner navigates to `/register` and signs up using `partner@dpik.com.my`.
   - The `RegistrationWhitelistMiddleware` validates the email against `allowed_registration_emails`, completes user registration, and generates the initial user record.
   - A non-whitelisted email (e.g. `unknown@gmail.com`) attempting registration is rejected with `403 Registration Restricted`.
3. **Execution**:
   - The new partner connects their individual Outlook account in settings.
   - All subsequent chats, personal notes, personal tasks, and presets are initialized in an isolated state scoped strictly to the partner's `auth()->id()`.
4. **Exit**: The partner accesses their private AI executive workstation while instantly sharing access to historical Project Register intelligence.

## [SCEN-08] Manual Bundle Review Without AI
1. **Entry**: A Bundle has just been retrieved (`Bundle 2`, 7 emails) and the executive prefers to read it themselves rather than ask the AI.
2. **Progress**: Executive opens Bundle 2 from the Sessions list or the Bundles screen.
3. **Execution**: The executive taps each retrieved email to expand and read it inline, then writes a short free-text note in the Bundle's "My notes" field (e.g. *"VO 2 looks fine, sign off Thursday"*) and saves it.
4. **Exit**: The Bundle now carries the executive's own note alongside the retrieved emails. No AI request was made, and none was required — this is a first-class path, not a fallback (per [`CAP-019`](CAPABILITIES.md#cap-019-bundle-based-retrieval-default-filter--auto-promotion-engine)). The executive can still tap "Ask Copilot about this Bundle" later without losing the note.

## [SCEN-09] Auto-Promoted Project Filter
1. **Entry**: Over the course of a week, the executive retrieves four separate Bundles that each happen to include mail concerning project `PC-2023-011`.
2. **Progress**: The system detects that `PC-2023-011` now accounts for more than 3 sessions/Bundles within the trailing 7 days.
3. **Execution**: Without any manual configuration, the retrieval filter picker surfaces `PC-2023-011` as a new quick-filter option, visually marked as auto-promoted (per [`CAP-019`](CAPABILITIES.md#cap-019-bundle-based-retrieval-default-filter--auto-promotion-engine)).
4. **Exit**: The executive can now retrieve or filter directly by `PC-2023-011` in one tap instead of relying on the default today/direct/new filter and manually searching. A project that later falls below the 3-sessions-in-7-days threshold is not force-demoted immediately — it simply stops being reinforced, keeping the rule's plumbing simple ([`ADR-022`](adr/ADR-022-bundle-based-retrieval-ai-optional-review-and-adaptive-navigation.md) records this as an open, revisitable choice rather than a settled edge case).
