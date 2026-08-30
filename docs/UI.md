# DPIK Tadbir: UI & Interaction Design Contract

## [UI-01] Archetype & Design Philosophy

DPIK Tadbir is designed under the **Executive Suite & Calm Governance Archetype**, synthesizing the proven design foundations of **Woodfire Premium Tier**, **ARH-URUS**, and **DPIK Tugas**.

It strikes the deliberate balance between two extremes:
- **Not a Toy Box**: Zero childish emojis, arcade icons, or gamified badges.
- **Not a Cold War Room**: Avoids harsh stark-black terminal grids, high-glare alarm borders, or sterile surveillance-style minimalism.
- **The Sweet Spot**: **Quiet Executive Luxury & Calm Operational Mastery**. Warm dark-charcoal surfaces, tactile card geometry, warm bone and champagne/bronze accents, dignified typography, and generous spatial rhythm.

---

## [UI-02] Shared Design Foundation (Synthesis of ARH Flagships)

| Dimension | Woodfire (Premium F&B) | ARH-URUS (Kinhold Suite) | DPIK Tugas (Consulting Core) | **DPIK Tadbir Executive Synthesis** |
| :--- | :--- | :--- | :--- | :--- |
| **Dark Palette Base** | Warm char-black (`#13100B`, `#1E1710`) | Warm deep charcoal (`#141311`, `#2A2724`) | Botanical dark graphite (`#0E1410`, `#131B16`) | **Warm Obsidian-Slate (`#111215`, `#18191E`, `#21232B`)** |
| **Light Palette Base** | Warm cream / bone (`#FAF3E8`) | Warm ivory / linen (`#FAF8F5`) | Natural paper (`#F4F7F3`, `#FFFFFF`) | **Warm Alabaster & Crisp Paper (`#F7F8FA`, `#FFFFFF`)** |
| **Primary Accent** | Ochre-gold & champagne (`#C9A36D`, `#D4B896`) | Refined sage / wisteria / ink | Forest emerald (`#17633A`, `#429A6A`) | **Champagne-Amber & Heritage Emerald (`#C9A36D`, `#3E885B`)** |
| **Text Contrast** | Warm bone (`#F2E6D0`) & clay (`#A8937A`) | Warm off-white (`#F0EDE9`) & stone (`#A09C97`) | Soft mint-gray (`#E4EDE7`) & muted sage | **Warm Off-White (`#F3F4F6`) & Polished Slate (`#9CA3AF`)** |
| **Borders & Dividers** | Hairline gold tint (`rgba(201,163,109,0.15)`) | Warm dark dividers (`#3C3833`) | Subtle forest lines (`#25332A`) | **Warm Graphite Hairlines (`#2C2F38` dark / `#E2E5EB` light)** |
| **Corner Geometry** | Soft organic curves (`12px–16px`) | Tactile rounded pills & sheets | Clean rounded cards (`14px–16px`) | **Polished Ergonomic Radii (`12px–16px` cards, `9999px` pills)** |

---

## [UI-03] Responsive Viewport Continuum

```text
┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 DESKTOP WORKSTATION (1280px+) - 3-COLUMN ADAPTIVE                      │
├──────────────┬───────────────────────────────────┬─────────────────────────────────────────────────────┤
│ 1. Nav Rail  │ 2. Context Filter & Stream Canvas │ 3. Detail & Interactive Workspace Canvas            │
│ (Collapsible │ (360px - 420px)                   │ (Flex-1 / 640px+)                                   │
│  220px/56px) │ - Lexical & FTS5 Search (Cmd+K)   │ - High-Density Project Register Inspector           │
│              │ - Executive Inquiries Pill Bar    │ - Markdown Notes / Draft Composer                   │
│ - Governance │ - Incoming Delta Briefing Feed    │ - Docked AI Copilot Review Drawer (380-440px)       │
│ - Projects   │ - Milestone & Claim Summaries     │   * High-Density Streaming Synthesis                │
│ - Register   │ - Livewire Filter Tabs            │   * Staged Action Cards [Approve / Edit / Discard]  │
│ - Workspace  │                                   │                                                     │
└──────────────┴───────────────────────────────────┴─────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                              MOBILE WEBAPP & TWA (320px - 767px) - THUMB-FIRST                         │
├────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [Top Context Bar] Title / Active Project Context / Sync Status (48px)                                  │
├────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [Main Viewport]                                                                                        │
│ - Single-Column Operational Stream (Delta Cards, Register Entries, Milestones)                        │
│ - Pull-to-Refresh Gesture (Graph API Delta Check)                                                     │
│ - Tactile Dossier Cards with Soft Shadows and Touch Affordances                                       │
├────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [Interactive Bottom Sheet / Copilot Surface] (Draggable Modal / Sheet with 44px+ touch targets)        │
│   * Preset Inquiries: [Today's Delta] [Unread Correspondence] [Delivery Blockers]                      │
│   * Action Card: [Approve & Dispatch] [Edit Proposal] [Discard]                                        │
├────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [Bottom Navigation Bar] (56px + env(safe-area-inset-bottom))                                           │
│   [Overview]       [Copilot]       [Register]       [Notes]       [Rollups]                            │
└────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 1. Desktop Workstation (1280px+)
- **Navigation Rail**: 220px expanded sidebar that collapses to a 56px icon rail. Navigation categories are organized into clean governance hubs.
- **Master-Detail Flow**: Smooth transitions between the left briefing stream and the primary document workspace.
- **Docked AI Copilot Drawer**: Docks gracefully to the right (380px–440px) with dedicated dossier cards and real-time streaming analysis.

### 2. Mobile Webapp & TWA (320px - 767px)
- **Thumb-Zone Bottom Bar**: Fixed 56px height + `env(safe-area-inset-bottom)` with smooth tab indicators.
- **Draggable Bottom Sheets**: Tactile bottom sheets with soft backdrop blur (`backdrop-blur-md`), smooth drag handles, and thumb-accessible primary action buttons ($\ge 44\text{px}$).
- **Pull-to-Refresh**: Natural top overscroll triggers an on-demand Microsoft Graph API delta sync.

---

## [UI-04] Design Tokens & Typographic Hierarchy

### 1. Surface & Border Tokens

| Element | Dark Mode (Default Executive) | Light Mode (Warm Ivory) | Token Rationale |
| :--- | :--- | :--- | :--- |
| **App Canvas** | `#111215` (Warm Obsidian) | `#F7F8FA` (Warm Alabaster) | Low-glare, non-fatiguing foundation. |
| **Surface Panel** | `#18191E` (Dark Slate-Noir) | `#FFFFFF` (Pure Card White) | Clear visual hierarchy and containment. |
| **Elevated Card** | `#21232B` (Polished Graphite) | `#FFFFFF` (Lifted with soft shadow) | Tactile material depth. |
| **Hairline Borders** | `#2C2F38` (Warm Dark Border) | `#E2E5EB` (Crisp Light Border) | 1px clean separation without harshness. |
| **Input / Inset** | `#141519` (Sunken Dark Fill) | `#F0F2F5` (Sunken Light Fill) | Natural visual affordance for forms. |

### 2. Dignified Semantic Color Mapping

- **Approved / Synchronized**: Soft Emerald (`text-[#429A6A] bg-[#429A6A]/10 border-[#429A6A]/20`).
- **Pending Human Review**: Warm Champagne-Amber (`text-[#D4A373] bg-[#D4A373]/10 border-[#D4A373]/25`).
- **Delivery Bottleneck / Action Required**: Refined Terracotta-Rose (`text-[#D35858] bg-[#D35858]/10 border-[#D35858]/25`).
- **AI Synthesis / System Context**: Polished Slate (`text-[#9CA3AF] bg-[#18191E] border-[#2C2F38]`).

### 3. Typographic System
- **Headings & Body UI**: Modern, clean sans-serif (`Inter`, `Plus Jakarta Sans`, or system `-apple-system, BlinkMacSystemFont, "Segoe UI"`).
- **Tabular Monospace Accents** (`Geist Mono`, `JetBrains Mono`):
  - Project reference codes (`PC-2023-011`, `JPS-KEL-04`).
  - Milestone revisions ($R_0, R_1, R_2$).
  - Financial claim valuations (`RM 120,000.00`).
  - Timestamps, ISO durations, and audit receipt IDs (`REC-8842`).

---

## [UI-05] Controlled Generative UI: Action Dossier Cards

The AI Copilot renders structured, typed **Action Dossier Cards** ([`ADR-007`](adr/ADR-007-write-safety-human-in-the-loop-approval-gates.md)) designed to resemble a formal executive sign-off brief:

```text
┌────────────────────────────────────────────────────────────────────────┐
│ OUTLOOK DISPATCH PROPOSAL                                 [STAGED]     │
├────────────────────────────────────────────────────────────────────────┤
│ To:        Dato' Ir. Azman (Minco Perunding)                           │
│ Subject:   Re: PC-2023-011 - Review of Interim Claim No. 4             │
│ Context:   Project Register [PC-2023-011: Sungai Udang Barrage]        │
├────────────────────────────────────────────────────────────────────────┤
│ Proposed Dispatch Text:                                                │
│ "Dear Dato' Ir. Azman,                                                 │
│                                                                        │
│ I have reviewed the revised site valuation report for Claim No. 4.     │
│ The geotechnical variation order of RM 120,000.00 is verified and      │
│ approved for Thursday submission."                                     │
├────────────────────────────────────────────────────────────────────────┤
│ [ Approve & Dispatch ]       [ Edit Proposal ]       [ Discard ]       │
└────────────────────────────────────────────────────────────────────────┘
```

### Action Card Lifecycle
1. **Staged Proposal State**: Renders with warm champagne-amber status, verified recipient metadata, and structured text preview.
2. **Interactive Confirmation**:
   - **`[Approve & Dispatch]`**: Solid high-contrast button (`bg-zinc-100 text-zinc-950 font-medium hover:bg-white`) generating a signed token for Graph API dispatch.
   - **`[Edit Proposal]`**: Subtle outline button for inline text refinement.
   - **`[Discard]`**: Muted ghost button to archive the proposal.
3. **Executed Receipt State**: Mutates in-place into an immutable **Executed Receipt Badge** with audit receipt link (`#REC-8842`).

---

## [UI-06] The 8 Core Runtime States Implementation

| State | UI Expression in DPIK Tadbir |
| :--- | :--- |
| **Idle** | Default loaded state with clean data tables, project register index, and preset inquiry buttons ready. |
| **Loading** | Restrained skeleton placeholders for records, pulsing hairline border during Outlook delta queries. |
| **Ready** | Fully populated data views with active search filters, badge counters, and operational metrics. |
| **Active** | Slide-over AI chat drawer open with streaming message tokens, active input textarea, and live tool invocation telemetry. |
| **Success** | Clean toast notification confirming email dispatched via Outlook, note created, or register updated. |
| **Empty** | Clear empty-state notice with primary action button (e.g. *"No pending action items — Select a preset to check today's inbox"*). |
| **Error** | Structured error callout with actionable retry button (e.g. Outlook OAuth re-authentication prompt). |
| **Unavailable** | 403 Forbidden / Session Expired modal with quick PIN/login unlock. |

---

## [UI-07] Mobile Webapp & TWA Touch Ergonomics

1. **Safe-Area Inset Management**:
   ```css
   .mobile-container {
     padding-top: env(safe-area-inset-top, 0);
     padding-bottom: env(safe-area-inset-bottom, 0);
     padding-left: env(safe-area-inset-left, 0);
     padding-right: env(safe-area-inset-right, 0);
   }
   ```
2. **Touch Targets & Hit-Box Padding**:
   - Minimum **$44 \times 44\text{px}$** bounding box for all interactive triggers.
   - At least **$8\text{px}$** separation between adjacent controls.
3. **Android Hardware Back-Button & Swipe Gesture Handling**:
   - Bottom sheets push transient history states (`history.pushState({ modal: 'action_card' }, '')`).
   - Hardware back button or swipe-back gestures close active sheets cleanly without navigating out of the webapp.
4. **Haptic Feedback**:
   - High-consequence actions (`[Approve & Dispatch]`) trigger short haptic vibration pulses (`navigator.vibrate?.(15)`) on supported devices.
5. **Offline Data Shell**:
   - Cached SQLite FTS5 project register summaries and personal notes remain searchable offline with a top-level `"Cached"` status pill.

---

## [UI-08] Information Display & Table UX Contract

Every list surface is a Filament v4 table resource (see [`DESIGN-07`](DESIGN.md)) and must implement the following baseline, modeled on mature admin platforms (Filament demo, Linear, Attio):

1. **Table Tabs with Badge Counts**: Each resource index opens with horizontal filter tabs carrying live record counts, so state distribution is visible before any click.
2. **Persistent Filters & Sort**: Filter, sort, and search state persist in the user session — returning to a list restores the previous working view.
3. **Toggleable Columns**: Secondary columns (timestamps, IDs, token counts) are hidden by default and user-toggleable via the column picker.
4. **Grouping & Summarizers**: Where a natural axis exists (project code, week), tables support grouping with footer summarizers (counts, RM totals).
5. **Row-Level Action Groups**: Primary action inline; secondary actions collapse into a `⋯` action group — never more than two visible buttons per row.
6. **Bulk Actions**: Multi-select with a sticky bulk-action bar (e.g. mark tasks done, archive notes). Destructive bulk actions always require a confirmation modal.
7. **Relative Time Display**: Timestamps under 7 days render relative (*"2 hours ago"*) with the absolute MYT datetime in a tooltip; older dates render absolute.

### Per-Resource Baseline

| Resource | Default Sort | Tabs (with badges) | Key Displayed Columns | Bulk Actions |
| :--- | :--- | :--- | :--- | :--- |
| **Project Register** | `updated_at` desc | All / Active / Recently Updated | Code (mono), Title, Last Entry, Entry Count, Updated | Export |
| **Personal Tasks** | Due date asc, priority | Open / Due Today / Overdue / Done | Title, Priority badge, Due (relative), Source link | Mark Done, Delete |
| **Personal Notes** | `updated_at` desc | All / By Tag / Email-Linked | Title, Tags (pills), Source Thread, Updated | Delete |
| **Activity Rollups** | Period desc | Daily / Weekly | Period, Actions Count, Highlights excerpt | Export |
| **Executive Presets** | Manual order | Mine / System | Name, Trigger phrase, Last Used | — |
| **Registration Whitelist** | `created_at` desc | Active / Revoked | Email (mono), Added By, Notes | Revoke |

---

## [UI-09] Global Search & Command Surface (`Cmd+K`)

1. **Scope**: Filament global search spans Project Register (code, title, entry text), Personal Notes (title, tags), Personal Tasks (title), and Activity Rollups.
2. **Result Details**: Each result carries a two-line detail — e.g. a register hit shows `PC-2023-011` (mono) plus the matched snippet and last-updated date — so the executive can disambiguate without opening records.
3. **Result Actions**: Register results expose inline quick actions: **[Open]** and **[Ask Copilot about this]** (pre-fills the Copilot drawer with the record as context).
4. **Preset Reachability**: Typing a preset name (e.g. *"delta"*) surfaces the matching Executive Preset as a runnable command, keeping one keyboard path to every high-frequency action.

---

## [UI-10] Notifications, Feedback & Undo

1. **Database Notification Bell**: Filament database notifications with an unread-count badge, polled every 30s. Notified events: rollup generated, delta sync completed with urgent items, action card awaiting approval, Outlook re-auth required.
2. **Navigation Badges**: Sidebar items carry live counts — pending action cards on *AI Assistant*, tasks due today on *Personal Tasks*.
3. **Toast Discipline**: Toasts confirm only user-initiated mutations (dispatched, saved, revoked) and auto-dismiss in 4s. Background events go to the bell, never as interruptive toasts.
4. **Undo Where Reversible**: Reversible mutations (note archived, task completed, preset deleted) render toasts with an **[Undo]** action for 6s. Irreversible dispatches (email sent) never offer undo — the approval gate ([`UI-05`](#ui-05-controlled-generative-ui-action-dossier-cards)) is the safeguard, and the receipt link is shown instead.

---

## [UI-11] Dashboard Composition

The dashboard answers *"what needs me today?"* in one screen, top-to-bottom by urgency:

1. **Stats Overview Row**: 4 stat cards with 7-day trend sparklines — *Unread Urgent Emails*, *Pending Action Cards*, *Tasks Due Today*, *Register Entries This Week*. Each card deep-links to its filtered list view.
2. **Pending Action Cards Widget**: Staged proposals awaiting approval, rendered as compact dossier rows with inline **[Review]**; polls every 30s.
3. **Today's Delta Widget**: Latest Outlook delta briefing summary with a **[Run Morning Briefing]** preset button when empty.
4. **Recent Rollups Widget**: Last 3 daily rollups as excerpt cards.
5. **Empty Dashboard State**: First-run dashboard renders a guided setup checklist (Connect Outlook → Run first preset → Pin a project) instead of blank widgets.

---

## [UI-12] QoL Conventions & Formatting Standards

1. **Locale & Formats (Malaysian Engineering Context)**:
   - Timezone: **MYT (UTC+8)** everywhere; dates as `29 Aug 2026, 2:20 PM`.
   - Currency: `RM 120,000.00` in tabular monospace, right-aligned in tables.
   - Project codes, receipt IDs, and revisions always monospace per [`UI-04`](#ui-04-design-tokens--typographic-hierarchy).
2. **Navigation QoL**:
   - Breadcrumbs on every detail page; record create/edit defaults to **slide-over** (list context preserved), full page only for the draft composer.
   - After create, redirect to the record **view** page (not the index).
   - Sticky table headers; pagination options 10 / 25 / 50, default 25.
3. **Keyboard Map (Desktop)**:
   - `Cmd+K` global search · `Cmd+J` toggle Copilot drawer · `/` focus table search · `Esc` closes topmost drawer/modal · `Cmd+Enter` submits the Copilot prompt.
4. **Accessibility**:
   - Visible focus rings on all interactive elements; text contrast ≥ 4.5:1 against every [`UI-04`](#ui-04-design-tokens--typographic-hierarchy) surface token.
   - `prefers-reduced-motion` disables streaming shimmer, pulse, and sheet-spring animations.
   - Streaming Copilot output announces via `aria-live="polite"`; action card approval buttons carry explicit `aria-label`s naming the consequence (*"Approve and send email to …"*).
5. **Language**: UI chrome is English; user content (notes, drafts, register entries) is bilingual-friendly (Bahasa Malaysia / English) with no translation framework in Phase 1.

---

## [UI-13] Executive Settings, Live Probes & Error Diagnostic UI (ADR-017)

Governed by [`ADR-017`](adr/ADR-017-runtime-integrations-and-graceful-error-fallback-guards.md) and [`docs/CONFIGURABLES.md`](CONFIGURABLES.md), the **Executive Settings** interface (`/admin/executive-settings`) serves as the calm control center for sovereign credentials and live provider diagnostics:

### 1. Visual Structure & Layout
- **Two-Column Card Organization**:
  - **Column 1: AI Provider Credentials**: Masked password inputs for Anthropic (`sk-ant-api03-...`) and Gemini (`AIzaSy...`) with live latency badges.
  - **Column 2: Microsoft 365 / Outlook Integration**: Form inputs for `microsoft_client_id` (UUID), `microsoft_client_secret` (masked), and `microsoft_tenant_id` (UUID).
- **Interactive Preflight Probes**:
  - Each section provides a dedicated **[Test Connection]** action that displays a live status badge:
    - 🟢 `Connected (214ms)`
    - 🟡 `Fallback Active (Gemini)`
    - 🔴 `Authentication Error`

### 2. Provider-Direct Error Diagnostic Cards
When a provider returns an authentication or validation failure, the UI renders a high-visibility, calm diagnostic card:
- **Exact Upstream Error Display**: Outputs the exact sanitized provider error message (e.g. `AADSTS7000215: Invalid client secret provided`).
- **Actionable Remediation Guide**: Displays numbered step-by-step instructions on where to generate or verify the credential in the provider's console.
- **Deep-Links**: Direct links to `console.anthropic.com`, `aistudio.google.com`, or `portal.azure.com`.

### 3. Real-Time Topbar & Copilot Sync
- Saving settings dispatches Livewire events (`executive-settings-saved`, `outlook-status-changed`).
- Global panel topbar and Copilot drawer (`Cmd+J`) badges update state immediately without page refreshes.
- If an executive invokes an email preset without Outlook configured, an inline warning CTA card seamlessly directs them to `/admin/executive-settings`.

---

## [UI-14] In-Chat Two-Tier Model Selector & Iconography Standards (ADR-018)

Governed by [`ADR-018`](adr/ADR-018-openrouter-multi-model-catalog-and-runtime-favorites-swapper.md) and [`docs/CONFIGURABLES.md`](CONFIGURABLES.md), the AI Copilot Drawer features a compact runtime model selector:

### 1. Two-Tier Visual Continuum
- **Compact Badge (At Rest)**:
  - Renders inside the drawer header as a subtle slate pill (`#18191E` with hairline `#2C2F38` border).
  - Displays exactly two items: the **Active Provider** and **Model Name** (e.g. `[ ⚡ Anthropic · Claude 3.7 Sonnet ▾ ]` or `[ ⚡ OpenRouter · DeepSeek R1 ▾ ]`).
- **Expanded 3-Favorites Quick-Switcher (On Click)**:
  - Expands an ephemeral popover showcasing the executive's 3 pre-configured favorite models with visual indicators (`Active`, `Select`).
  - Clicking any option immediately swaps the active model for subsequent conversation turns, auto-collapsing the menu in 1 click.
  - Bottom action link: `[ ⚙️ Configure Favorites in Settings → ]`.

### 2. Strict Iconography Standards ([`UI-01`](#ui-01-archetype--design-philosophy))
- **Anti-Emoji Doctrine**: Strict enforcement of the "Not a Toy Box" rule. Zero childish emojis, gaming icons, or arcade badges in core navigation and model switcher chrome.
- **First-Party Vector Icons**: Strictly utilizes thin-stroke **Heroicons v2 Outline** (`heroicon-o-cpu-chip`, `heroicon-o-sparkles`, `heroicon-o-chevron-down`) and minimal geometric status dots (`#429A6A` emerald, `#C9A36D` champagne-amber).


