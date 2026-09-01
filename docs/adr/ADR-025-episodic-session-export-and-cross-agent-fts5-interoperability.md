# ADR-025: Episodic Session Export Architecture & Cross-Agent FTS5 Archive Interoperability

**Status**: Accepted  
**Date**: 2026-09-01  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
AI chat sessions in DPIK Tadbir contain critical episodic memory: executive decisions, tender negotiations, project milestone commitments, and multi-turn tool trajectories. To extract high-level patterns and composed actions without building complex analytics microservices inside the application, the system requires a standard export mechanism that enables:
1. Cross-agent search across terminal CLI tools (Claude, Antigravity, Kimi, Codex) via `arh-session-reader`.
2. Convenient transport via Taildrop, Google Drive, or local storage.
3. Machine-readable JSONL streaming for data pipelines.

## Decision
1. **Dual-Format Session Export Engine (`SessionExportService`)**:
   - **SQLite FTS5 Archive (`.db`)**: Formatted strictly to the canonical `arh-session-reader` schema:
     - `sessions` table: `slug`, `provider: 'tadbir'`, `agent_label: 'executive-copilot'`, `intent`, `decision_marker`, `started_at`, `updated_at`.
     - `turns` table: `turn_no`, `role`, `text` (including appended tool calls and results), `ts`.
     - `turns_fts` virtual table: Tokenized using Porter stemmer with automated triggers (`turns_ai`, `turns_au`, `turns_ad`) and WAL mode.
     - `health` table: Provider health heartbeat tracking.
   - **JSON Lines Archive (`.jsonl`)**: Emits structured JSON objects per session with complete turns, tool call arguments, results, and metadata.
2. **Standardized Filename Contract**:
   - Multi-session export: `{app_name}-sessions-{YYYY-MM-DD}.{db|jsonl}` (e.g. `dpik-tadbir-sessions-2026-09-01.db`).
   - Single-session export: `{app_name}-session-{id}-{YYYY-MM-DD}.{db|jsonl}`.
3. **Multi-Channel Invocations**:
   - **Artisan CLI**: `php artisan session:export [--format=db|jsonl|all] [--session=ID] [--output=PATH] [--stdout]`.
   - **Authenticated HTTP Endpoint**: `/admin/sessions/export/{format?}` enabling 1-click browser downloads.
4. **Decision Marker Heuristics**:
   - Scans session content for natural language decision phrases (`decided to`, `we will choose`, `architectural decision`, `action items:`, `approved to send`) and indexes `decision_marker = 1`.

## Consequences
- **Positive**: Direct interoperability with `arh session search -p tadbir` across the entire ARH ecosystem.
- **Positive**: Zero new microservice footprint — leverages SQLite FTS5 for zero-cost offline full-text search.
- **Positive**: Secure offline backups shareable over Taildrop without database dumps.
