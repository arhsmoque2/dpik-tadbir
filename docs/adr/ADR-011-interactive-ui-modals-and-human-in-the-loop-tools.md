# ADR-011: Interactive UI Modals, Option Pickers & Human-in-the-Loop Tool Contracts

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
In executive workflows, the AI frequently encounters situations requiring human steering, clarification, option selection, or explicit sign-off before proceeding. For example:
- Choosing which project an email thread belongs to when multiple candidates match.
- Selecting a tone or strategy for a client response (e.g. *Option A: Concede to revised schedule*, *Option B: Enforce contractual penalty clause*).
- Confirming high-stakes actions (sending an outbound Outlook email, reassigning engineering tickets, committing register milestones).

Relying on plain text conversation to handle structured choices introduces conversational friction, ambiguity, and parsing errors. The AI requires **first-class interactive UI tools** that render native Filament modals, choice buttons, and approval cards.

## Decision
1. **Interactive AI Tool Primitives (`laravel/mcp` / Livewire)**:
   Equip the AI assistant with 3 dedicated interactive UI tools:

   - **`ask_user_question` (Choice Picker & Clarification Modal with Escape Hatches)**:
     - **Purpose**: Render an interactive modal or inline choice component with selectable options, non-mutually exclusive custom notes, and full escape hatches.
     - **Non-Mutually Exclusive "Other / Notes"**: The freeform text input is **supplementary**, meaning the user can select a predefined option (e.g. *Option 1: Sungai Udang*) **AND** provide additional text/refinements (e.g. *"Ensure Engineer A is CC'd"*), or provide only text without selecting an option.
     - **Escape Hatches**: Includes a dedicated **`[Skip]`** button (passes control back to the AI without a choice) and a **`[Cancel / Dismiss]`** button.
     - **Tool Invocation Schema**:
       ```json
       {
         "question": "Multiple projects match this drawing submittal. Which project should this update be linked to?",
         "options": ["PC-2023-011: Sungai Udang Barrage", "PC-2023-015: JKR Sedenak Johor"],
         "is_multi_select": false,
         "allow_custom_input": true,
         "custom_input_placeholder": "Add specific instructions, refinements, or other project..."
       }
       ```
     - **Result Returned to AI**:
       ```json
       {
         "action": "submitted",
         "selected_options": ["PC-2023-011: Sungai Udang Barrage"],
         "custom_input": "Also notify Engineer A to review sheet 4 by Wednesday"
       }
       ```

   - **`propose_action_card` (Interactive Approval Card)**:
     - **Purpose**: Render a staged action card in the chat drawer with side-by-side previews, diffs, recipients, attachments, and explicit confirmation triggers.
     - **Buttons**: **`[Approve & Dispatch]`** (Green primary), **`[Edit Draft In-Place]`** (Blue secondary), **`[Discard]`** (Subtle ghost).
     - **Payload Schema**:
       ```json
       {
         "action_type": "outlook_reply",
         "target_entity": "Client X (JKR Johor)",
         "preview": { "to": "director@jkr.gov.my", "subject": "Re: Site Survey Phase 2", "body": "..." },
         "consequences": "Will send email directly from your authenticated Outlook account."
       }
       ```

   - **`request_confirmation` (Binary Confirmation Dialog with Skip)**:
     - **Purpose**: Quick confirmation modal for medium-stakes operations (e.g., *“Save these 4 extracted commitments to the Sungai Udang Project Register?”*). Buttons: **`[Confirm]`**, **`[Skip]`**, **`[Cancel]`**.

2. **Asynchronous Agent State Machine (Pause & Resume)**:
   - When the AI invokes an interactive tool, `AgentService` does **not** terminate the session. It transitions the session state to `AWAITING_USER_INPUT` or `AWAITING_CONFIRMATION`.
   - The Filament Livewire / Alpine.js frontend renders the modal popup or interactive card.
   - Upon user selection, text entry, or skip, the Livewire component emits a `resumeWithToolResult` event back to `AgentService`.
   - The AI receives the exact user selection and custom input as a structured `tool_result` and resumes its reasoning loop without losing conversational context.

3. **Configurability in Settings**:
   - Auto-modal vs. inline card preference toggle.
   - Option to allow inline editing on action cards before dispatch.
   - Default visibility of custom notes field in question modals.

## Consequences
- **Positive**: Complete flexibility; users are never forced into an all-or-nothing binary choice and can freely annotate or skip.
- **Positive**: Eliminates conversational back-and-forth for structured choices; zero ambiguity in user intent.
- **Positive**: 100% fail-safe; high-stakes actions pause execution until explicitly approved via native UI controls.
- **Positive**: Seamless user experience matching modern native applications.
