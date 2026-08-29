# ADR-007: Write Safety & Human-in-the-Loop Action Confirmation Gates

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Granting an AI agent the ability to draft, reply, and forward emails through the Managing Director's real Outlook account presents immense productivity advantages, but also catastrophic risk if executed autonomously without human confirmation. A single hallucinated reply or unauthorized email dispatch can cause major client or legal damages. Strict, configurable write-safety gates are mandatory.

## Decision
1. **Interactive Action Cards for All Outbound Mutations**:
   - Any tool call that mutates external communication state (`outlook_create_draft`, `outlook_reply`, `outlook_forward`, `reassign_ticket`) must generate an interactive **Action Card** in the UI.
   - The card displays:
     - Target recipients (`To`, `Cc`, `Bcc`)
     - Subject line
     - Full proposed body diff / preview
     - Attached files / thread history
     - **[Approve & Dispatch]** (Primary green trigger) & **[Discard / Edit]** (Ghost trigger)
   - The tool execution pauses and will **never** dispatch over Graph API until the user explicitly clicks the confirmation button.
2. **Expose Write Safety Policies in Settings**:
   - **Outbound Email Dispatch Policy**: Dropdown policy (`Always Require Approval`, `Allow Draft Staging Only`, `Strict Sign-Off`).
   - **Pre-Approved Internal Recipient Domains**: Email domains (e.g. `dpik.com.my`) where internal forwardings require lightweight 1-click confirmation.
   - **Project Register Mutation Gate**: Toggle switch requiring confirmation before saving summaries into `project_registry_entries`.
   - **Ticket Reassignment Approval Gate**: Toggle switch requiring confirmation before mutating team ticket assignments.
   - **Default Executive Email Signature**: Configurable rich text / HTML signature template appended to approved outbound messages.

## Consequences
- **Positive**: Complete elimination of unauthorized outbound emails and catastrophic hallucinated communications.
- **Positive**: The Managing Director retains absolute sovereignty over all outbound correspondence.
- **Trade-off**: Requires one manual click per outbound email dispatch.
