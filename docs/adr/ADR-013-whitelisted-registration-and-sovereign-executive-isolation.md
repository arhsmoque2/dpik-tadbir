# ADR-013: Whitelisted Executive Registration & Multi-User Sovereign Workspace Isolation

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Initial design iterations framed DPIK Tadbir as a strictly single-user personal instance for the Managing Director. However, operational expansion requires enabling other senior leaders, partners, and designated executives to access the platform while maintaining strict data sovereignty.

Opening registration publicly is an unacceptable security risk, but hardcoding a single fixed user limits team enablement. Furthermore, each registered executive requires their own completely isolated personal workspace, Outlook mailbox connection settings, private AI chat sessions, personal notes, personal tasks, and activity ledgers, while retaining collaborative access to the company-wide **Project Register** domain knowledge.

## Decision

1. **Email Whitelist Registration Gate & Two-Tier Role Split**:
   - User registration is strictly gated by an **Email Whitelist** mechanism (`allowed_registration_emails` table and `REGISTRATION_WHITELIST` environment fallback).
   - Only email addresses explicitly added to the whitelist (configured by the platform operator) are permitted to create an account.
   - The platform enforces an explicit **two-tier role split**:
     - **`super_admin` (Platform Operator)**: Manages the registration whitelist (`allowed_registration_emails`), configures system-wide settings (`AiSettings`, `OutlookSettings`, `SafetySettings`), and administers infrastructure.
     - **`user` (Whitelisted Registrants)**: Every registered executive (including the Managing Director and Executive Admin) operates with full sovereign workspace privileges and shared Project Register access, but has zero administrative control over the whitelist or global settings. Organizational seniority in the company does not map to application administrative tiers.
   - Any registration attempt from a non-whitelisted email is rejected immediately with a 403 Forbidden error before creating any database records.

2. **Sovereign Multi-User Executive Isolation**:
   - Each registered executive operates within their own **sovereign workspace** with strict `auth()->id()` isolation:
     - **Private Outlook Connection Settings**: Each executive connects their own individual Outlook account via `outlook-mcp` with isolated OAuth credentials in the OS keyring / encrypted user settings.
     - **Private AI Chat Sessions & History**: `chat_sessions` and `chat_messages` are strictly scoped to `auth()->id()`.
     - **Private Personal Notes & Tasks**: `personal_notes` and `personal_tasks` are private to each user and governed by `PersonalNotePolicy` and `PersonalTaskPolicy`.
     - **Private Executive Presets & Personalization**: Each executive can customize and toggle their own `executive_presets` and personal tone/preference profiles (`user_personalization_profiles`).
     - **Private Action Receipts & Rollups**: `ai_action_receipts` log actions executed by that specific user, powering personalized daily and weekly activity rollups.

3. **Shared Enterprise Project Knowledge (Project Register)**:
   - The **Project Register** (`project_registry_entries`, SQLite FTS5 index) acts as the shared, compounding company-wide intelligence repository.
   - All registered executives can search, query, and contribute processed email summaries and commitments to the Project Register, with all entries stamped with `recorded_by_user_id` for attribution and auditability.

4. **Zero Inter-User Data Leakage Invariant**:
   - No executive can view, search, or mutate another executive's private chat sessions, Outlook email delta scans, personal notes, tasks, or action receipts.
   - Filament resource queries and MCP tool calls automatically enforce global `where('user_id', auth()->id())` query scopes.

## Consequences
- **Positive**: Controlled, secure onboarding for multiple designated company executives without open public registration risks.
- **Positive**: Complete data sovereignty and confidential privacy for each executive's email workflows and personal notes.
- **Positive**: Compounding shared company memory via the unified Project Register.
- **Trade-off**: Requires super admin or operator intervention to whitelist new executive emails.
