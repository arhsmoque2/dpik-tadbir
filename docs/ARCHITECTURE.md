# DPIK Tadbir: Architecture

## [ARCH-01] System Overview
DPIK Tadbir is structured as a full-stack Laravel 12 application with a Filament v4 administrative control plane, combining in-process MCP server/client services, asynchronous queue workers for email and background jobs, and a SQLite / PostgreSQL relational database.

```text
┌────────────────────────────────────────────────────────────────────────────┐
│                             DPIK TADBIR WORKSTATION                        │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  [Presentation Layer]                                                      │
│  • Filament v4 Admin Panel (Livewire / Alpine.js / Tailwind CSS)           │
│  • AI Assistant Drawer & Chat Workspace Panel (ARH-URUS Port)              │
│  • Resonator Inbox View (Threads, Folders, Email Reader)                   │
│                                                                            │
│  [Application Services Layer]                                              │
│  • AgentService (Multi-Turn AI Loop + Anti-Hallucination Guard)            │
│  • ToolRegistry (laravel/mcp Adapter for in-app & external tool execution) │
│  • EmailSyncService (Delta sync & thread grouping)                         │
│                                                                            │
│  [Domain & Persistence Layer (Eloquent)]                                   │
│  • Staff: User, Department, Position, PositionAssignment                   │
│  • Projects: Project, Epic, Ticket, DeliverableRevision                    │
│  • Notes & Tasks: PersonalNote, PersonalTask                               │
│  • Email: ResonatorThread, ResonatorEmail, ResonatorFolder                 │
│  • AI & Audit: ChatSession, ChatMessage, ActivityLog                       │
│                                                                            │
│  [Integration & Boundary Layer]                                            │
│  • MCP Stdio/HTTP Client ──► outlook-mcp (Python Graph API Worker)         │
│  • LLM Client ──► Anthropic Claude / Google Gemini / OpenAI                │
│  • MCP Server Endpoint (/mcp) ──► External Agents (Cursor, Claude Code)    │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

## [ARCH-02] Storage Schema & Relationships
- **Personal Notes (`personal_notes`)**: `id`, `user_id`, `title`, `content` (Markdown), `tags` (JSON), `source_email_id` (nullable), `source_thread_id` (nullable), `project_id` (nullable), timestamps.
- **Personal Tasks (`personal_tasks`)**: `id`, `user_id`, `title`, `description`, `due_date`, `is_done`, `priority`, timestamps.
- **Chat Sessions (`chat_sessions`)**: `id`, `user_id`, `title`, `provider`, `model`, `total_tokens`, timestamps.
- **Chat Messages (`chat_messages`)**: `id`, `chat_session_id`, `role`, `content`, `tool_calls` (JSON), `tool_results` (JSON), `tokens`, timestamps.
- **Email Threads & Emails (`resonator_threads`, `resonator_emails`)**: Multi-folder, threaded messages synced from Outlook/Gmail.

## [ARCH-03] AI & MCP Execution Boundary
1. The **`AgentService`** executes tool calls through **`ToolRegistry`**.
2. Tools inherit from `Laravel\Mcp\Server\Tool`.
3. Read tools (`OutlookSearchTool`, `ReadEmailTool`, `ListProjectsTool`, `GetStaffWorkloadTool`) execute instantly with zero side-effects.
4. Write tools (`CreateDraftTool`, `CreateNoteTool`, `CreateTaskTool`, `ReassignTicketTool`, `SendEmailTool`) validate permissions and, when required, construct interactive confirmation payloads for the user.

## [ARCH-04] Security & Credential Isolation
1. **Zero Raw Token Exposure**: Microsoft Graph tokens are stored in the OS Credential Vault via `outlook-mcp`.
2. **Personal Privacy**: `PersonalNote` and `PersonalTask` are strictly user-scoped with no admin bypass.
3. **Audit Trail**: Every AI tool invocation is logged with input arguments, output status, and execution duration.
