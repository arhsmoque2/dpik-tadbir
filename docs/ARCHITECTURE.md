# DPIK Tadbir: Architecture

## [ARCH-01] System Overview
DPIK Tadbir is structured as a full-stack Laravel 12 application with a Filament v4 administrative control plane. It acts as an **AI Email Processing & Synthesis Layer** over the Managing Director's existing Outlook account, connecting via `outlook-mcp` (Graph API) on-demand and storing only **processed intelligence** (summaries, action items, project register entries, and audit receipts)—never duplicating raw email storage.

```text
┌────────────────────────────────────────────────────────────────────────────┐
│                             DPIK TADBIR WORKSTATION                        │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  [Presentation Layer]                                                      │
│  • Filament v4 Admin Panel (Livewire / Alpine.js / Tailwind CSS)           │
│  • Executive AI Assistant Drawer & Presets Bar ("What's new today?")       │
│  • Project Register & Weekly Activity Rollup Dashboards                    │
│  • Personal Notes & Tasks Management Panel                                 │
│                                                                            │
│  [Application Services Layer]                                              │
│  • AgentService (Multi-Turn AI Loop + Anti-Hallucination Guard)            │
│  • OutlookMcpBridgeService (Connects to local Python outlook-mcp server)   │
│  • ProjectRegistryService (Context accumulator & domain knowledge indexer) │
│  • ActionMemoryService (Action receipts & daily/weekly rollup generator)   │
│  • ToolRegistry (laravel/mcp Adapter for in-app & external tool execution) │
│                                                                            │
│  [Domain & Persistence Layer (Eloquent)]                                   │
│  • Processed Intelligence: ProjectRegistryEntry, ProjectContextSummary     │
│  • Operations: Project, Epic, Ticket, Department, Position, User           │
│  • Personal Workspace: PersonalNote, PersonalTask                          │
│  • AI & Audit: ChatSession, ChatMessage, AiActionReceipt, AuditLog         │
│                                                                            │
│  [Integration & Boundary Layer]                                            │
│  • MCP Stdio Client ──► outlook-mcp Server (Python Graph API / OS Keyring) │
│  • LLM Client ──► Anthropic Claude / Google Gemini / OpenAI                │
│  • MCP Server Endpoint (/mcp) ──► External Agents (Cursor, Claude Code)    │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

## [ARCH-02] Storage Schema & Relationships (Processed Outputs Only)
- **Project Register (`project_registry_entries`)**:
  - `id`, `project_id`, `source_type` (outlook_search, email_summary, manual_briefing), `source_outlook_id` (nullable string), `title`, `summary` (Markdown), `key_commitments` (JSON), `action_items` (JSON), `recorded_by_user_id`, timestamps.
- **AI Action Receipts & Memory (`ai_action_receipts`)**:
  - `id`, `user_id`, `session_id`, `action_type` (draft, reply, forward, summarize, create_note, create_task, update_register), `target_entity_type`, `target_entity_id`, `summary`, `payload` (JSON), `is_confirmed`, `executed_at`, timestamps.
- **Personal Notes & Tasks (`personal_notes`, `personal_tasks`)**:
  - `personal_notes`: `id`, `user_id`, `title`, `content` (Markdown), `tags` (JSON), `source_outlook_id` (nullable), `project_id` (nullable), timestamps.
  - `personal_tasks`: `id`, `user_id`, `title`, `description`, `due_date`, `is_done`, `priority`, timestamps.
- **Chat Sessions & Messages (`chat_sessions`, `chat_messages`)**:
  - `chat_sessions`: `id`, `user_id`, `title`, `provider`, `model`, `total_tokens`, timestamps.
  - `chat_messages`: `id`, `chat_session_id`, `role`, `content`, `tool_calls` (JSON), `tool_results` (JSON), `tokens`, timestamps.

## [ARCH-03] AI & Outlook MCP Execution Boundary
1. The **`AgentService`** executes tool calls through **`ToolRegistry`**.
2. **Read / Scan Tools** (`outlook_list_inbox_delta`, `outlook_search_mail`, `outlook_read_message` with `concise=True`):
   - Directly query the MD's Outlook mailbox via `outlook-mcp`.
   - Results are fed into the LLM context for synthesis and discarded from transient memory once processed.
3. **Write / Mutation Tools** (`outlook_create_draft`, `outlook_reply`, `outlook_forward`, `CreateNoteTool`, `SaveToProjectRegisterTool`):
   - Generate an **Interactive Action Card** in the UI.
   - Require explicit human confirmation before executing via Graph API or database write.
4. Confirmed actions commit an `AiActionReceipt`, enriching the AI's searchable action memory and feeding daily/weekly rollups.

## [ARCH-04] Security & Sovereign Data Boundaries
1. **Zero Raw Email Duplication**: No raw email text or attachments are copied to the database.
2. **OS Keyring Authentication**: Microsoft Graph API tokens are managed securely in the Windows Credential Store via `outlook-mcp`.
3. **Strict Policy Scoping**: `PersonalNote` and `PersonalTask` are strictly user-isolated.
4. **Immutable Audit Trail**: All actions produce permanent receipts for governance and compliance.

## [ARCH-05] Hybrid Project Memory & Retrieval Subsystem (ARH Session Reader Pattern)
The application integrates a dedicated high-performance search subsystem modeled directly after the **ARH Session Reader**:

```text
  ┌────────────────────────────────────────────────────────────────────────┐
  │                   PROJECT REGISTER & MEMORY RETRIEVAL                  │
  ├────────────────────────────────────────────────────────────────────────┤
  │                                                                        │
  │  [Query Router & Parser]                                               │
  │  • Natural language query + metadata filters (e.g. project:PC-2023-011)│
  │  • Heuristic markers extractor (--decisions, --since 30d, --commitments│
  │                                                                        │
  │  [Dual Retrieval Paths]                                                │
  │  ┌───────────────────────────────┐   ┌──────────────────────────────┐  │
  │  │ Lexical Path (SQLite FTS5)    │   │ Metadata & Recency Filter    │  │
  │  │ • BM25 scoring over title,    │   │ • Exact project_id match     │  │
  │  │   summary, commitments        │   │ • Date-decay / status active │  │
  │  │ • Porter stemmer + unicode61  │   │ • dm:decision / dm:blocker   │  │
  │  └───────────────┬───────────────┘   └──────────────┬───────────────┘  │
  │                  │                                  │                  │
  │                  └───────────────┬──────────────────┘                  │
  │                                  ▼                                     │
  │  [Reciprocal Rank Fusion (RRF) & Reranking Engine]                     │
  │  • Fuses lexical ranks with chronological and project-relevance weight │
  │  • Eliminates duplicates across multiple entries for the same project  │
  │                                  │                                     │
  │                                  ▼                                     │
  │  [High-Density Context Formatter]                                      │
  │  • Emits compact pipe-delimited context cards:                         │
  │    "2026-08-20 | project:PC-2023-011 | dm:decision | Drawing rev B ok"│
  │  • Ingests 20+ historical project items into <500 LLM tokens           │
  │                                                                        │
  └────────────────────────────────────────────────────────────────────────┘
```

1. **SQLite FTS5 Indexing**:
   - Virtual tables (`project_registry_entries_fts`, `personal_notes_fts`, `ai_action_receipts_fts`) updated via triggers on INSERT/UPDATE/DELETE.
2. **Decision-Marker Heuristics (`dm:hit`)**:
   - Matches structured milestones, agreed change orders, and delivery commitments.
3. **High-Density Output Contract**:
   - Standardized output format enables instant semantic recall by the AI without blowing up context window token limits.
