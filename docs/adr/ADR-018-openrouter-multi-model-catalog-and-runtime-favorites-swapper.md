# ADR-018: OpenRouter Unified Multi-Model Gateway & In-Chat 3-Favorites Runtime Swapper

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

---

## Context

DPIK Tadbir previously supported direct integration with Anthropic (Claude 3.7 Sonnet) and Google Gemini (2.5 Flash). While this provided resilience via two distinct API gateways, executive workflows frequently require access to specialized models across diverse providers (e.g. DeepSeek R1 for deep mathematical logic, Claude 3.5/3.7 for nuanced prose, or Gemini for high-speed batch summaries).

Furthermore, switching models required leaving the active conversation, navigating to `/admin/executive-settings`, editing settings, and returning to the chat drawer. This created significant context-switching friction during fast-paced executive decision making.

Following research into the `andrecorugda/ai-openrouter-gateway` pattern, we need a unified multi-model catalog and an in-chat runtime model swapper that preserves active conversation state while complying strictly with the **Executive Suite & Calm Governance Archetype** ([`UI-01`](../UI.md)).

---

## Decision

1. **OpenRouter Unified Gateway Integration**:
   - Add first-class support for **OpenRouter** (`openrouter_api_key` stored encrypted at rest via AES-256) alongside native Anthropic and Google Gemini keys.
   - Enables instant access to over 200+ models (Claude 3.7, DeepSeek R1, GPT-4o, Llama 3.3) via a single unified API endpoint with automated upstream load balancing.

2. **Top-3 Favorite Models Configuration**:
   - Executives can configure their **Top 3 Favorite Models** in `/admin/executive-settings`:
     - **Favorite Slot 1 (Default)**: e.g., `Anthropic · Claude 3.7 Sonnet (Hybrid Reasoning)`
     - **Favorite Slot 2**: e.g., `OpenRouter · DeepSeek R1 (Complex Logic & Calculation)`
     - **Favorite Slot 3**: e.g., `Google · Gemini 2.5 Flash (Ultra High-Speed Summarization)`
   - Stored in the `users` table or user personalization profile.

3. **In-Chat Two-Tier Model Selector in Copilot Drawer (`Cmd+J`)**:
   - **Compact Header Badge (At Rest)**: Displays a clean, minimal 2-item indicator showing only the active Provider and Model:
     `[ ⚡ Anthropic · Claude 3.7 Sonnet ▾ ]` or `[ ⚡ OpenRouter · DeepSeek R1 ▾ ]`.
   - **Expanded Quick-Switch Popover (On Click)**: Drops down the **3 Favorites Quick-Switcher**:
     - Clicking any of the 3 favorite buttons instantly swaps the active model for subsequent prompts in the current session.
     - Auto-collapses immediately upon selection to maintain zero clutter.
     - Deep-link button to open the full settings panel.

4. **Strict Iconography & Visual Governance Compliance ([`UI-01`](../UI.md))**:
   - Strict adherence to the "Not a Toy Box" doctrine: Zero childish emojis or arcade badges.
   - All UI controls strictly utilize thin-stroke Heroicons (`heroicon-o-cpu-chip`, `heroicon-o-sparkles`, `heroicon-o-chevron-down`) and refined geometric status dots.

---

## Consequences

- **Positive**: Access to the entire OpenRouter multi-model ecosystem with a single sovereign API key.
- **Positive**: Zero-reload, single-click runtime model swapping directly from the Copilot drawer without losing conversational context or active Action Cards.
- **Positive**: Streamlined, clutter-free executive UI presenting high-signal information (Provider + Model) at a glance.
- **Positive**: Complete doc-chain synchronization across [`docs/CAPABILITIES.md`](../CAPABILITIES.md), [`docs/CONFIGURABLES.md`](../CONFIGURABLES.md), [`docs/DESIGN.md`](../DESIGN.md), and [`docs/UI.md`](../UI.md).
- **Trade-off**: Requires tracking model usage and token budgets across OpenRouter and native direct provider APIs.
