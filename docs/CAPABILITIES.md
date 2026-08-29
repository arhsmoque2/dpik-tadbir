# DPIK Tadbir: Capabilities

## [CAP-001] Multi-Provider AI Agent Loop
The system must provide an iterative agent execution loop (`AgentService`) supporting Anthropic Claude, Google Gemini, and OpenAI with token tallying, session persistence, and anti-hallucination validation gates.

## [CAP-002] Outlook / Gmail MCP Tool Integration
The system must act as an MCP client connecting to `outlook-mcp` (Graph API) and Gmail services, exposing typed tools to search, delta-sync, read, draft, and send emails securely using OS-keyring stored credentials.

## [CAP-003] Executive Personal Notes Engine
The system must maintain private, encrypted `PersonalNote` records with full Markdown support, auto-generated tags, and explicit foreign backlinks to source email threads (`source_email_id`, `source_thread_id`) and project entities.

## [CAP-004] Personal Task & To-Do Execution
The system must support private `PersonalTask` items with priority, due date, status toggling, and fast creation directly from AI chat extracts.

## [CAP-005] Project & Staff Oversight Engine
The system must model corporate structure (`Department`, `Position`, `PositionAssignment`, `User`) and project hierarchies (`Project`, `Epic`, `Ticket`, `DeliverableRevision`) to calculate real-time staff workload and project progress.

## [CAP-006] Visual Inbox & Thread Management (Filament Resonator)
The system must provide a visual Filament web interface for viewing synced email threads, folders, unread indicators, star flags, and attachments alongside the AI conversational interface.

## [CAP-007] Explicit Human-in-the-Loop Proposal & Write Safety
The system must enforce that any destructive or external mutation (sending an email, deleting tickets, mass reassignments) produces a structured proposal requiring explicit operator confirmation before dispatch.

## [CAP-008] In-Panel & External MCP Server Exposure (`laravel/mcp`)
The system must expose internal resources and tools over a standard `/mcp` endpoint and via internal `ToolRegistry`, allowing both in-app AI chat and external desktop agents (Cursor, Claude Code) to query company state under strict RBAC policies.

## [CAP-009] Comprehensive Audit Logging
Every action executed by the AI or operator must be recorded in an immutable activity log capturing timestamp, actor, target entity, previous state, and resulting state.
