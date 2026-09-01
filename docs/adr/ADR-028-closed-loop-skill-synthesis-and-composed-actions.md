# ADR-028: Closed-Loop Skill Synthesis, Composed Domain Actions, and Self-Improving Learning Loop (Hermes Pattern)

**Status**: Accepted  
**Date**: 2026-09-01  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Standard AI agent interactions suffer from high latency and token waste when orchestrating repetitive workflows using low-level primitive tools (e.g. `search_mail` → `read_message` → `create_personal_task` → `create_draft`). A 4-step sequence consumes ~4 model round-trips (~6,000 tokens) and takes 12–18 seconds of user wait time. Furthermore, models reset their learning state after each session, forcing executives to re-explain their multi-step workflows.

Inspired by Nous Hermes Agent closed learning loops and modern Filament AI extensions (`filament-copilot`, `laravel-filament-solaris`, `aipromptbuilder`), Tadbir requires an architecture that collapses primitives into single-turn compound actions and autonomously synthesizes reusable skills through executive collaboration.

## Decision
1. **Composed Domain Actions (Single-Turn Atomic Tools)**:
   - Collapse recurring primitive sequences into single high-level compound tools:
     - `prepare_email_reply_draft`: Atomically searches email, parses thread headers (`In-Reply-To`, `References`), drafts executive content, and saves to Drafts in 1 single turn.
     - `tender_auto_intake`: Extracts milestones from tender emails, creates a Project Register record, and sets a personal task in 1 turn.
   - **Impact**: Reduces latency from ~15s to ~2s and cuts token consumption by ~85% on repeat actions.
2. **Proactive Skill Promotion Prompt (Hermes Closed-Loop Pattern)**:
   - When a multi-step trajectory completes successfully with user approval, the AI Copilot detects the pattern and offers:
     > *"💡 I noticed we just executed: `search_mail` → `read_message` → `create_draft`. Would you like to save this as a 1-click Quick Action named 'Tender Reply & Intake'?"*
3. **Interactive Workflow Optimization Conversation**:
   - The user can adjust parameters conversationally (*"Yes, but always set tone to formal and CC engineering@dpik.com.my"*).
   - The AI compiles the verified prompt into an `ExecutivePreset` record.
4. **Quick Action Pills in Copilot Drawer**:
   - Promoted skills surface as 1-tap interactive pills (`[⚡ Morning Triage]`, `[📋 Pending Approvals]`, `[🔍 JKR Tender Scan]`) directly above the chat drawer input for instant execution.
5. **Resource-Aware Context Auto-Injection (`filament-copilot` Pattern)**:
   - Copilot automatically detects the active Filament page/record (e.g. Project or Email Bundle) and injects its metadata into context, eliminating manual copy-pasting.

## Consequences
- **Positive**: 85% token cost reduction on common operational tasks.
- **Positive**: Sub-2-second execution speed for complex workflows.
- **Positive**: The system continuously self-improves through natural human-in-the-loop collaboration.
- **Positive**: Zero clutter — low-value workflows remain ad-hoc; high-value workflows compound into 1-tap skills.
