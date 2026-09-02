# DPIK Tadbir: Architecture

## [ARCH-01] System Overview
DPIK Tadbir is structured as a full-stack Laravel 12 application with a Filament v4 administrative control plane. It acts as an **AI Email Processing & Synthesis Layer** over authorized executives' Outlook accounts, connecting via `outlook-mcp` (Graph API) on-demand and storing only **processed intelligence** (summaries, action items, project register entries, and audit receipts)—never duplicating raw email storage.

```text
┌────────────────────────────────────────────────────────────────────────────┐
│                             DPIK TADBIR WORKSTATION                        │
│             (Whitelisted Multi-Executive Sovereign Workspaces)             │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  [Presentation Layer]                                                      │
│  • Filament v4 Admin Panel (Livewire / Alpine.js / Tailwind CSS)           │
│  • Executive AI Assistant Drawer & Presets Bar ("What's new today?")       │
│  • Project Register (CAP-005) & Activity Rollup Dashboards                 │
│  • Personal Notes & Tasks Workspace Panel (Per-Executive Scoped)           │
│  • [Deferred per ADR-012]: Multi-Department Staff & Ticketing Boards      │
│                                                                            │
│  [Application Services Layer]                                              │
│  • AgentService (Multi-Turn AI Loop + Anti-Hallucination Guard)            │
│  • MailBridge (Per-user IMAP/SMTP mailbox connector)                       │
│  • RegistrationWhitelistService (Email whitelist registration guard)       │
│  • MemoryRetrievalService (SQLite FTS5 BM25 + Reciprocal Rank Fusion)      │
│  • ActionMemoryService (Action receipts & daily/weekly rollup generator)   │
│  • PresetExecutionService (User-scoped dynamic prompt template engine)     │
│  • ToolRegistry (laravel/mcp Adapter for in-app & external tool execution) │
│  • [Deferred per ADR-012]: ProjectOversightService & StaffWorkloadService  │
│                                                                            │
│  [Domain & Persistence Layer (Eloquent)]                                   │
│  • Shared Knowledge: ProjectRegistryEntry, ProjectContextSummary           │
│  • Whitelist & Auth: AllowedRegistrationEmail, User                        │
│  • Sovereign Workspace: PersonalNote, PersonalTask, ExecutivePreset        │
│  • AI & Audit: ChatSession, ChatMessage, AiActionReceipt, AuditLog         │
│  • [Deferred per ADR-012]: Project, Epic, Ticket, Department, Position     │
│                                                                            │
│  [Integration & Boundary Layer]                                            │
│  • MCP Stdio Client ──► outlook-mcp Server (Python Graph API / OS Keyring) │
│  • LLM Client ──► Anthropic Claude / Google Gemini / OpenAI                │
│  • MCP Server Endpoint (/mcp) ──► External Agents (Bearer Token Scoped)    │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

## [ARCH-02] Storage Schema & Relationships (Processed Outputs Only)

### Active Core Schema
- **Registration Whitelist (`allowed_registration_emails`)**:
  - `id`, `email` (unique string), `notes` (e.g. "Managing Director", "Senior Partner"), `is_active` (boolean), `whitelisted_by_user_id`, timestamps.
- **Users & Authentication (`users`)**:
  - `id`, `name`, `email`, `role` (`super_admin` | `user`), `is_hq` (boolean filter flag), timestamps.
- **Project Register (`project_registry_entries`)** *(Shared Enterprise Knowledge)*:
  - `id`, `project_code` (e.g. `PC-2023-011`), `project_title`, `source_type` (outlook_search, email_summary, manual_briefing), `source_outlook_id` (nullable string), `title`, `summary` (Markdown), `key_commitments` (JSON), `action_items` (JSON), `recorded_by_user_id`, timestamps.
- **AI Action Receipts & Memory (`ai_action_receipts`)** *(User-Scoped)*:
  - `id`, `user_id`, `session_id`, `action_type` (draft, reply, forward, summarize, create_note, create_task, update_register), `target_entity_type`, `target_entity_id`, `summary`, `payload` (JSON), `is_confirmed`, `executed_at`, timestamps.
- **Personal Notes & Tasks (`personal_notes`, `personal_tasks`)** *(User-Scoped)*:
  - `personal_notes`: `id`, `user_id`, `title`, `content` (Markdown), `tags` (JSON), `source_outlook_id` (nullable), `project_code` (nullable), timestamps.
  - `personal_tasks`: `id`, `user_id`, `title`, `description`, `due_date`, `is_done`, `priority`, timestamps.
- **Executive Presets (`executive_presets`)** *(User-Scoped with System Seeds)*:
  - `id`, `user_id` (nullable for system seed defaults, foreign key to `users`), `title`, `slug`, `icon`, `color`, `prompt_template`, `lookback_window`, `target_scope`, `output_format`, `sort_order`, `is_active`, timestamps.
- **Chat Sessions & Messages (`chat_sessions`, `chat_messages`)** *(User-Scoped)*:
  - `chat_sessions`: `id`, `user_id`, `title`, `provider`, `model`, `total_tokens`, timestamps.
  - `chat_messages`: `id`, `chat_session_id`, `role`, `content`, `tool_calls` (JSON), `tool_results` (JSON), `tokens`, timestamps.

### Deferred Schema Models (Preserved for Phase 2 Resumption per [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md))
- `projects`, `epics`, `tickets`, `departments`, `positions`, `position_assignments`.

---

## [ARCH-03] Domain Model Taxonomy (The Malaysian Engineering Split)

To maintain strict alignment with local Malaysian engineering consultancy and infrastructure workflows while preserving codebase cleanliness:

1. **"Tugas" (Domain Umbrella Concept)**:
   - User-facing positioning and navigation category term. Signals purpose-built engineering consultancy workflows.
   - *Guardrail*: Never used as an internal schema table or Eloquent model name ([`OPP-001`](OPPORTUNITIES.md)).
2. **Action / Aksi (`actions` / `personal_tasks`)**:
   - Lightweight, fast operational tasks (e.g. email reply, site coordination, statutory document query submission).
3. **Deliverable (`deliverables` - Phase 2)**:
   - Milestone-driven technical work products (Inception Report, Hydraulic Design, Tender BQ, Interim Claim Valuation) with formal revision lifecycles ($R_0, R_1, R_2, \dots$).
4. **PIC (Person-In-Charge)**:
   - Statutory/governance accountability role (BEM registered Professional Engineer / Lead Consultant sign-off), distinct from the operational assignee.

---

## [ARCH-04] AI & Outlook MCP Execution Boundary
1. The **`AgentService`** executes tool calls through **`ToolRegistry`** within the context of `auth()->user()`.
2. **Read / Scan Tools** (`outlook_list_inbox_delta`, `outlook_search_mail`, `outlook_read_message` with `concise=True`, `query_project_register`):
   - Directly query the executive's Outlook mailbox via `outlook-mcp` using user-specific credentials, or query local SQLite/FTS5 indexes.
   - Results are fed into the LLM context for synthesis and discarded from transient memory once processed.
3. **Write / Mutation Tools** (`outlook_create_draft`, `outlook_reply`, `outlook_forward`, `create_personal_note`, `create_personal_task`, `commit_project_register`):
   - Generate an **Interactive Action Card** in the UI via `propose_action_card`.
   - Require explicit human confirmation before executing via Graph API or database write.
4. Confirmed actions commit an `AiActionReceipt`, enriching the user's searchable action memory and feeding personalized daily/weekly rollups.

---

## [ARCH-05] Security, Registration Whitelist & Sovereign Data Boundaries
1. **Email Whitelist Registration Gate**: Account registration validates against `allowed_registration_emails` via `RegistrationWhitelistMiddleware` ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)). Unauthorized emails are rejected with 403 Forbidden.
2. **Zero Raw Email Duplication**: No raw email text or attachments are copied to the database.
3. **OS Keyring Authentication**: Microsoft Graph API tokens are managed securely in the Windows Credential Store / encrypted storage per executive.
4. **Sovereign Executive Privacy**: `PersonalNote`, `PersonalTask`, `ChatSession`, and `AiActionReceipt` are strictly user-isolated (`PersonalNotePolicy`, `PersonalTaskPolicy`).
5. **External MCP Token Authentication**: The `/mcp` endpoint validates bearer tokens for external agent sessions (Cursor, Claude Code) with tool-level permissions.
6. **Immutable Audit Trail**: All actions produce permanent receipts for governance and compliance.

---

## [ARCH-06] Hybrid Project Memory & Retrieval Subsystem (ARH Session Reader Pattern)
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
  │  │ • BM25 scoring over title,    │   │ • Exact project_code match   │  │
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

---

## [ARCH-06] Infrastructure & Environment Contract

The runtime environment, external cloud integrations, and secret management boundaries are formalized in [`docs/ENVIRONMENT.md`](ENVIRONMENT.md):

- **GCP & Cloud Run**: Multi-stage FrankenPHP containerization deployed to `asia-southeast1` using Workload Identity Federation (WIF).
- **Neon Serverless PostgreSQL**: Dual-endpoint connection model separating direct connections (for Cloud Run migration jobs) from pooled connections (for web runtime traffic).
- **Multi-Provider AI Fallback**: Dual-provider hierarchy (Anthropic Claude 3.7 Sonnet primary, Google Gemini 2.5 Flash fallback) prioritizing user-configured private keys over system credentials.
- **Outlook MCP Gateway**: Stdio-based subprocess execution over Microsoft Graph API with write-safety approval tokens.
