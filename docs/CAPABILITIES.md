# DPIK Tadbir: Capabilities

*Posture Note: DPIK Tadbir is architected as a **whitelisted multi-executive platform** ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)). Only explicitly whitelisted email addresses can register. Each registered executive operates within their own sovereign private workspace (isolated Outlook mailbox, chat sessions, personal notes, tasks, and presets) while sharing the company-wide continuous Project Register. Multi-tier organizational staff hierarchies and employee ticketing are deferred ([`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)).*

## [CAP-001] Multi-Provider AI Agent Loop
The system must provide an iterative agent execution loop (`AgentService`) supporting Anthropic Claude, Google Gemini, and OpenAI with token tallying, session persistence, and anti-hallucination validation gates (`AntiHallucinationGuard`).

## [CAP-002] Outlook MCP Email Processor Bridge (`outlook-mcp`)
The system must act as an MCP client connecting to `outlook-mcp` (Graph API) with OS-keyring token lifecycle management. The AI executes on-demand typed tools (`OutlookSearchMailTool`, `OutlookListInboxDeltaTool`, `OutlookReadMessageTool` with `concise=True`) to search, read, and delta-check Outlook emails without storing raw emails locally.

## [CAP-003] Executive Presets & Dynamic Quick Action Engine
The system must provide user-scoped, database-backed executive presets (`ExecutivePreset` model seeded with default system templates: *"What's new today?"*, *"Check my email today"*, *"Action items requiring reply"*, *"Project updates & blockers"*). Clicking a preset invokes `outlook-mcp` delta tools and passes concise context to the LLM to generate instant executive summaries and actionable recommendations.

## [CAP-004] Supervised Email Actions (Draft, Reply, Forward)
The system must empower the AI to stage email drafts (`OutlookCreateDraftTool`), compose contextual replies (`OutlookReplyTool`), and assemble forwards (`OutlookForwardTool`) via `outlook-mcp`. Every outbound action generates an interactive confirmation card requiring explicit human approval before being dispatched through Outlook.

## [CAP-005] Processed Output & Project Register Store
The system must persist exclusively **processed outputs** (summaries, extracted commitments, action items) rather than raw emails. Every search and summary is categorized under a **Project Register** entry (`projects`, `project_registry_entries`), compounding the AI's long-term domain knowledge across all authorized executives.

## [CAP-006] Action Memory & Rolling Audit Summaries
The system must record every AI-assisted action (emails summarized, drafts created, replies sent, notes saved, tasks created) into an immutable activity ledger (`AiActionReceipt` / `AuditLog`). This ledger serves as the AI's episodic memory and auto-generates rolling daily and weekly executive activity summaries per user.

## [CAP-007] Executive Personal Notes & Tasks Engine
The system must maintain private, encrypted `PersonalNote` records with Markdown support and backlinks to Outlook message IDs, alongside a `PersonalTask` checklist generated seamlessly from email action items, strictly isolated to `auth()->id()`.

## [CAP-008] Project & Staff Oversight Engine
- **Status**: `Deferred (Keep In View / KIV)` — *See [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)*.
- The system specification reserves contracts to model organizational structure (`Department`, `Position`, `PositionAssignment`) and project work items (`Project`, `Epic`, `Ticket`) via dedicated services (`ProjectOversightService`, `StaffWorkloadService`, `ReassignTicketTool`) to provide visibility into staff capacity and bottlenecks upon Phase 2 resumption.

## [CAP-009] Executive AI Command Center & Visual Panel (Filament v4)
The system must provide a visual Filament web interface centered on a **Bundle/Session list** (AI model, provider, retrieval timestamp, source Bundle, report-exists indicator) — not a metrics dashboard. Stat-card widgets (`ExecutiveStatsOverview` and similar) are **not** part of the default view; a widget is added only once it traces to a concrete need in [`INTENT.md`](INTENT.md) or an approved scenario in [`SCENARIOS.md`](SCENARIOS.md), per [`ADR-022`](adr/ADR-022-bundle-based-retrieval-ai-optional-review-and-adaptive-navigation.md). The panel also provides drawer-based Copilot chat, quick preset bars, and project register oversight views. *(Note: Multi-department staff workload widgets are deferred per [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md))*

## [CAP-010] In-Panel & External MCP Server Exposure (`laravel/mcp`)
The system must expose internal resources and tools over a standard `/mcp` endpoint and internal `ToolRegistry`. Access control is enforced at the external MCP gateway using scoped bearer tokens for external agent sessions (Cursor, Claude Code), while internal panel actions operate under the authenticated executive session.

## [CAP-011] Hybrid SQLite FTS5 Project Register Search Engine (ARH Session Reader Pattern)
The system must maintain dedicated SQLite FTS5 virtual tables (`project_registry_entries_fts`, `personal_notes_fts`, `ai_action_receipts_fts`) with unicode61/porter tokenizers to provide sub-millisecond lexical full-text search across all historical summaries, commitments, project records, and action receipts.

## [CAP-012] Decision & Commitment Marker Heuristics (`dm:hit`)
The system must parse and tag stored register entries with structured heuristic markers (`dm:decision`, `dm:commitment`, `dm:deadline_shift`, `dm:blocker`, `dm:financial`). This allows the AI or executive to filter specifically for architectural decisions and project milestones without semantic drift.

## [CAP-013] Reciprocal Rank Fusion (RRF) & Chronological Reranking
The search engine must combine lexical FTS5 BM25 match scores with recency weighting and project scoping using Reciprocal Rank Fusion ($RRF = \sum \frac{1}{60 + \text{rank}}$), ensuring active project statuses outrank superseded historical discussions while preserving complete searchable archives.

## [CAP-014] High-Density Structured Memory Output Contract
The system must format retrieved historical context into dense, token-efficient pipe-delimited context snippets (`date | project:code | dm:type | commitments | snippet`), allowing the AI to ingest dozens of relevant historical project records in minimal tokens before generating answers.

## [CAP-015] Continual Executive Personalization & Behavioral Adaptation Engine
The system must support automated periodic reflection over user interaction histories to synthesize an evolving **Executive Persona & Preference Profile** (prose vs. bullet preferences, tone nuances, recurring temporal inquiry habits, drafting styles). The profile must be toggleable, fully viewable and manually editable by the user, and injected into the AI context at runtime to allow the assistant to naturally grow and adapt to the executive's working style.

## [CAP-016] Interactive UI Modals, Choice Pickers & Escape Hatch Contracts
The system must equip the AI with interactive UI tools (`AskUserQuestionTool`, `ProposeActionCardTool`) featuring dedicated **escape hatches** (`[Skip]`, `[Cancel]`) and **non-mutually exclusive supplementary freeform input** ("Other / Notes / Custom Directives"). Users can select predefined options *and/or* append custom freeform text. When invoked, the execution loop pauses in an `AWAITING_INPUT` state, collects the operator's structured selection via Livewire, and resumes the reasoning loop without loss of conversational state.

## [CAP-017] Whitelisted Registration & Sovereign Workspace Isolation Engine
The system must restrict registration strictly to authorized email addresses (`allowed_registration_emails` table and `RegistrationWhitelistService`) operating under an explicit **two-tier role model** ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)):
- **`super_admin` (Platform Operator)**: Manages the registration whitelist, provisions global system settings (`AiSettings`, `OutlookSettings`, `SafetySettings`), and configures system infrastructure.
- **`user` (Whitelisted Registrants)**: Every registered executive (including the Managing Director and Executive Admin) receives their own sovereign workstation with fully isolated Outlook mailbox credentials, private chat sessions, personal notes, personal tasks, custom presets, and activity rollups, plus shared collaborative access to the Project Register—with zero administrative access over the whitelist or global system settings. Organizational seniority does not map to application permission tiers.

## [CAP-018] OpenRouter Multi-Model Gateway & In-Chat 3-Favorites Runtime Swapper
The system must support the **OpenRouter unified API gateway** ([`ADR-018`](adr/ADR-018-openrouter-multi-model-catalog-and-runtime-favorites-swapper.md)) alongside native Anthropic and Gemini provider credentials. The system enables executives to configure **Top 3 Favorite Models** in `/admin/executive-settings` and provides an in-chat two-tier model switcher in the Copilot Drawer header (`Cmd+J`):
- **Compact State (At Rest)**: Displays a minimal 2-item badge showing active Provider and Model (`[ ⚡ Provider · Model ▾ ]`).
- **Expanded State (On Click)**: Drops down a 1-click quick-switch popover between the 3 configured favorite models, immediately swapping the active reasoning model mid-session without losing conversational context or active Action Cards.
- **Visual Compliance**: Strictly follows the Calm Governance Archetype ([`UI-01`](UI.md)) using thin-stroke Heroicons and geometric status dots (zero childish emojis or arcade badges).

## [CAP-019] Bundle-Based Retrieval, Default Filter & Auto-Promotion Engine
The system must materialize every Outlook retrieval as a **Bundle**: a durable, named, human-readable record of the emails matched by one filter execution (e.g. `Bundle 1`, `Bundle 2`), distinct from an AI chat session. A Bundle is independently viewable and annotatable — a user opens a Bundle, reads each retrieved email, and writes free-text notes on the Bundle without invoking the AI ([`INTENT-03`](INTENT.md#intent-03-core-operating-principles) item 7).
- **Default filter** (used whenever no preset is explicitly selected): mail sent **today**, addressed **directly** to the executive (`To:`, excluding `CC:`), and **new since the last retrieval only** (a delta fetch — read/unread state is not a filter criterion).
- **Selectable filters**: the executive may choose alternate presets (e.g. last 48 hours, a specific project code, include CC) from a filter picker; the active filter/Bundle is always visibly indicated at the point of retrieval.
- **Auto-promotion rule**: once a single project accounts for more than 3 sessions or Bundles within a rolling 7-day window, that project is automatically surfaced as a quick filter (no manual configuration required) — see [`SCENARIOS.md`](SCENARIOS.md) for the worked example.
- **Optional AI action**: a Bundle exposes an explicit, opt-in "Ask Copilot about this Bundle" action (preset prompts: summarize the Bundle, draft a reply, find a specific email); this action is additive and never required to read or use a Bundle's contents.

## [CAP-020] Adaptive Mobile Navigation with Fixed Copilot Anchor
On mobile/TWA viewports, the primary navigation rail (`UI-03`) is replaced by a bottom bar. **Copilot occupies a fixed center slot on every configuration and is never reassignable or hideable.** The remaining four slots are user-customizable:
- **Reorder**: any of the six candidate destinations (Home, Retrieve, Sessions, Bundles, Notes, Projects, Settings — Copilot excluded) can be reordered via a dedicated Customize Navigation screen.
- **Show/hide**: the user chooses which destinations occupy the four visible slots; once more destinations are enabled than slots available, the lowest-priority ones collapse behind a **More (`•••`)** overflow control that opens a bottom drawer listing them.
- **Home as an expandable menu**: tapping Home does not navigate directly — it expands a compact menu of the full destination set, functioning as the nav bar's own "show me everything" affordance rather than a fixed single destination.
- **Retrieve as a compound control**: the Retrieve icon triggers an immediate fetch against the currently active filter; a separate, smaller chip beneath it names that active filter and opens the full filter picker on tap. The chip's label updates immediately whenever a different filter is selected, so the active filter is visible without opening the picker.

