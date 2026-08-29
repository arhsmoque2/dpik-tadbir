# DPIK Tadbir: UI & Interaction Design Contract

## [UI-01] Layout & Navigation Structure
DPIK Tadbir uses Filament's responsive sidebar layout paired with an always-accessible **AI Assistant Floating Slide-Over Drawer** (or bottom sheet on mobile).

### Main Navigation Groups
1. **Executive Hub**:
   - `Dashboard`: High-level company KPIs, urgent email count, active project blockers.
   - `AI Assistant`: Dedicated full-screen conversational workstation.
2. **Communications**:
   - `Inbox`: Threaded email viewer (Resonator), starred messages, folder switcher.
   - `Drafts & Sent`: Outbound messages awaiting approval or completed.
3. **Executive Workspace**:
   - `Personal Notes`: Markdown notes with tags, email thread backlinks, and quick-filter chips.
   - `Personal Tasks`: Private todo checklist with priority flags and calendar due dates.
4. **Operations & Projects**:
   - `Projects`: Project index, progress bars, delivery health status.
   - `Epics & Tickets`: Ticket boards, assignments, blocker tags.
5. **Staff & Organization**:
   - `Staff Directory`: User list, active position assignments, current workload indicators.
   - `Departments & Positions`: Organizational hierarchy.

## [UI-02] The 8 Core Runtime States Implementation
Every view adheres strictly to the 8 runtime UI states:

| State | UI Expression in DPIK Tadbir |
| :--- | :--- |
| **Idle** | Default loaded state with interactive Filament tables and action buttons enabled. |
| **Loading** | Tailwind skeleton cards for tickets, shimmering rows for email inbox and notes. |
| **Ready** | Fully populated data views with active search filters and badge counters. |
| **Active** | Slide-over AI chat drawer open with streaming message tokens and active input textarea. |
| **Success** | Filament toast notification confirming email sent, note created, or ticket reassigned. |
| **Empty** | Helpful empty-state illustration with primary action button (e.g. *"No unread emails. Start an AI inquiry"*). |
| **Error** | Red inline field callouts or danger banners with actionable retry buttons (e.g. Graph token re-auth). |
| **Unavailable** | 403 Forbidden / Session Expired modal with quick PIN/login unlock. |

## [UI-03] AI Chat Proposal Surface (Write Safety)
When the AI generates an action (Send Email, Reassign Ticket, Create Note):
1. The message renders a structured **Proposal Card**.
2. Shows:
   - Target entity and proposed changes (diff / preview).
   - Warning indicators if destructive.
   - **[Approve & Execute]** (Green primary button) and **[Discard]** (Subtle ghost button).
3. On approval, the card transitions to a disabled **Executed Badge** with timestamp and audit receipt link.
