# DPIK Tadbir: Capabilities

## [CAP-001] Multi-Provider AI Agent Loop
The system must provide an iterative agent execution loop (`AgentService`) supporting Anthropic Claude, Google Gemini, and OpenAI with token tallying, session persistence, and anti-hallucination validation gates.

## [CAP-002] Outlook MCP Email Processor Bridge (`outlook-mcp`)
The system must act as an MCP client connecting to `outlook-mcp` (Graph API) with OS-keyring token lifecycle management. The AI executes on-demand typed tools (`outlook_search_mail`, `outlook_list_inbox_delta`, `outlook_read_message` with `concise=True`) to search, read, and delta-check Outlook emails without storing raw emails locally.

## [CAP-003] Executive Presets & Smart Email Scans
The system must provide one-click executive presets (*"What's new today?"*, *"Check my email today"*, *"Action items requiring reply"*, *"Project updates & blockers"*). Clicking a preset invokes `outlook-mcp` delta tools and passes concise context to the LLM to generate instant executive summaries and actionable recommendations.

## [CAP-004] Supervised Email Actions (Draft, Reply, Forward)
The system must empower the AI to generate email drafts, compose contextual replies, and assemble forwards via `outlook-mcp`. Every outbound action generates an interactive confirmation card requiring explicit human approval before being dispatched through Outlook.

## [CAP-005] Processed Output & Project Register Store
The system must persist exclusively **processed outputs** (summaries, extracted commitments, action items) rather than raw emails. Every search and summary is categorized under a **Project Register** entry (`projects`, `project_registry_entries`), compounding the AI's long-term domain knowledge.

## [CAP-006] Action Memory & Rolling Audit Summaries
The system must record every AI-assisted action (emails summarized, drafts created, replies sent, notes saved, tasks created) into an immutable activity ledger (`AiActionReceipt` / `AuditLog`). This ledger serves as the AI's episodic memory and auto-generates rolling daily and weekly executive activity summaries.

## [CAP-007] Executive Personal Notes & Tasks Engine
The system must maintain private, encrypted `PersonalNote` records with Markdown support and backlinks to Outlook message IDs, alongside a `PersonalTask` checklist generated seamlessly from email action items.

## [CAP-008] Project & Staff Oversight Engine
The system must model organizational structure (`Department`, `Position`, `PositionAssignment`) and project work items (`Project`, `Epic`, `Ticket`) to provide real-time visibility into staff capacity and delivery bottlenecks.

## [CAP-009] Visual Command Center & Preset Dashboard (Filament v4)
The system must provide a visual Filament web interface with interactive tables, badges, drawer-based AI chat, quick preset bars, and project register oversight views.

## [CAP-010] In-Panel & External MCP Server Exposure (`laravel/mcp`)
The system must expose internal resources and tools over a standard `/mcp` endpoint and internal `ToolRegistry`, enabling in-app AI chat and desktop agents (Cursor, Claude Code, Antigravity) to query company records under strict RBAC policies.

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
The system must equip the AI with interactive UI tools (`ask_user_question`, `propose_action_card`, `request_confirmation`) featuring dedicated **escape hatches** (`[Skip]`, `[Cancel]`) and **non-mutually exclusive supplementary freeform input** ("Other / Notes / Custom Directives"). Users can select predefined options *and/or* append custom freeform text. When invoked, the execution loop pauses in an `AWAITING_INPUT` state, collects the operator's structured selection via Livewire, and resumes the reasoning loop without loss of conversational state.
