# DPIK Tadbir: UI & Interaction Design Contract

## [UI-01] Layout & Navigation Structure
DPIK Tadbir uses Filament's responsive sidebar layout paired with an always-accessible **AI Assistant Floating Slide-Over Drawer** and top-level **Executive Preset Bar**.

### Main Navigation Groups
1. **Executive Hub**:
   - `Dashboard`: High-level company KPIs, weekly action summary count, urgent project blockers.
   - `AI Assistant`: Dedicated full-screen conversational workstation with instant preset chips (*"What's new today?"*, *"Check my email today"*, *"Action items needing reply"*).
   - `Activity Rollups`: Comprehensive daily and weekly logs of completed AI actions, drafts, replies, and notes.
2. **Project Intelligence**:
   - `Project Register`: Categorized repository of client commitments, site summaries, and correspondence indexed per project.
   - `Projects`: Project index, progress bars, delivery health status.
   - `Epics & Tickets`: Ticket boards, assignments, blocker tags.
3. **Executive Workspace**:
   - `Personal Notes`: Markdown notes with tags, Outlook email backlinks, and quick-filter chips.
   - `Personal Tasks`: Private todo checklist with priority flags and calendar due dates.
4. **Organization**:
   - `Staff Directory`: User list, active position assignments, current workload indicators.
   - `Departments & Positions`: Organizational hierarchy.

## [UI-02] The 8 Core Runtime States Implementation
Every view adheres strictly to the 8 runtime UI states:

| State | UI Expression in DPIK Tadbir |
| :--- | :--- |
| **Idle** | Default loaded state with interactive Filament tables, project register index, and preset chips ready. |
| **Loading** | Tailwind skeleton cards for tickets, shimmering rows for register entries, loading pulse during Outlook delta queries. |
| **Ready** | Fully populated data views with active search filters and badge counters. |
| **Active** | Slide-over AI chat drawer open with streaming message tokens and active input textarea. |
| **Success** | Filament toast notification confirming email dispatched via Outlook, note created, or register updated. |
| **Empty** | Helpful empty-state illustration with primary action button (e.g. *"Select an Executive Preset to check today's emails"*). |
| **Error** | Red inline field callouts or danger banners with actionable retry buttons (e.g. Outlook re-auth prompt). |
| **Unavailable** | 403 Forbidden / Session Expired modal with quick PIN/login unlock. |

## [UI-03] AI Chat Proposal Surface (Write Safety)
When the AI generates an action (Draft Email, Reply, Forward via Outlook, Reassign Ticket, Create Note, Save to Project Register):
1. The message renders a structured **Interactive Action Card**.
2. Shows:
   - Target entity and proposed payload (diff / preview of subject, recipients, body).
   - Project register categorization tags.
   - **[Approve & Dispatch]** (Green primary button) and **[Discard]** (Subtle ghost button).
3. On approval, the card transitions to a disabled **Executed Badge** with timestamp and audit receipt link.
