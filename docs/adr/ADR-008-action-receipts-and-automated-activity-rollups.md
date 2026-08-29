# ADR-008: Immutable Action Receipts & Automated Activity Rollup Engine

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Leadership needs a clear, verifiable record of everything completed by the AI or operator over the course of each day and week. Without an automated ledger of completed actions, leadership lacks accountability over dispatched emails, created notes, and project changes, and the AI cannot recall its own prior decisions when queried in subsequent sessions.

## Decision
1. **Immutable Action Ledger (`ai_action_receipts`)**:
   - Every confirmed action (email drafted, reply sent, forward dispatched, personal note saved, task created, ticket updated) automatically commits an immutable receipt row.
   - Schema: `id`, `user_id`, `session_id`, `action_type`, `target_entity_type`, `target_entity_id`, `summary`, `payload` (JSON), `is_confirmed`, `executed_at`, timestamps.
2. **Episodic Action Memory for AI Assistant**:
   - The AI assistant is equipped with an internal query tool (`GetPastActionReceiptsTool`) that allows it to look up prior actions to answer questions like *"What did we reply to Vendor Y last Tuesday?"*.
3. **Automated Daily & Weekly Activity Rollups**:
   - Scheduled background jobs aggregate receipts into formatted executive reports.
4. **Expose Activity & Rollup Configurability in Settings**:
   - **Daily Rollup Trigger Schedule**: Configurable cron/time (default: `Weekdays at 18:00`).
   - **Weekly Rollup Trigger Schedule**: Configurable cron/time (default: `Fridays at 17:00`).
   - **Rollup Delivery Channels**: Checkbox options (`In-App Dashboard Banner`, `Personal Note Generation`, `Email Digest`).
   - **Audit Receipt Retention Period**: Dropdown (default: `Indefinite / Forever`, options: `90d`, `180d`, `365d`, `Forever`).
   - **Cost & Token Tracking Logging**: Toggle switch to track token usage and estimated cost per model.

## Consequences
- **Positive**: Complete executive audit trail and automatic weekly summary generation for permanent records.
- **Positive**: The AI gains genuine memory over its past decisions and completed tasks.
- **Trade-off**: Requires database storage indexing for high-volume receipt querying over time.
