# ADR-010: Continual Executive Personalization & Behavioral Adaptation Engine

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
Standard enterprise AI assistants remain static and impersonal across months of use, treating the Managing Director identically on day 100 as on day 1. While the **Project Register** stores objective company domain facts, it does not capture the Managing Director's subjective working style, formatting preferences (e.g., prose vs. bullet points), timing rhythms, tone, or decision habits. To make the AI truly "grow with the user", a continual, reflective personalization engine is needed—while maintaining strict user sovereignty, transparency, and manual editability.

## Decision
1. **Separation of Domain Memory vs. Behavioral Persona Memory**:
   - **Project Domain Memory (`project_registry_entries`)**: Objective facts, technical commitments, client correspondence, and drawing statuses.
   - **User Personalization Profile (`user_personalization_profiles`)**: Subjective working patterns, stylistic preferences, recurring temporal habits, and drafting nuances.
2. **Weekly Reflection & Behavioral Profiling Pipeline**:
   - A scheduled weekly reflection job (`BehavioralReflectionJob`) inspects user prompts, completed actions, edits made to draft replies, and recurring inquiry times over the past $N$ days.
   - Synthesizes a structured profile across 4 dimensions:
     1. **Communication & Formatting Preferences** (e.g., *"Prefers concise narrative prose over bullets for executive summaries"*, *"Prefers formal Bahasa Melayu greetings in tender drafts"*).
     2. **Operational Rhythms & Temporal Habits** (e.g., *"Checks Sedenak project progress every Monday morning"*).
     3. **Decision & Delegation Patterns** (e.g., *"Always assigns CAD drafting tickets to Engineer B"*).
     4. **Outbound Phrasing Style** (e.g., *"Prefers closing emails with 'Sekian, terima kasih' and direct PDF attachments"*).
3. **Full User Sovereignty & Manual Editability**:
   - **Global Toggle**: Master switch to enable/disable personalization injection with one click.
   - **Editable Profile UI**: Exposed in Filament under **Settings $\rightarrow$ Executive Personalization** (or User Profile tab).
   - The user can view the AI-generated observations, edit the text freely, add bespoke custom rules, or delete inaccurate assumptions.
4. **Runtime Prompt Injection Subsystem**:
   - When enabled, `AgentService` injects the compact persona block (capped at ~150–250 tokens) between the Master System Prompt and the Dynamic Project Context:
     ```text
     [USER WORKING STYLE & PREFERENCES]
     • Style: Concise narrative prose; minimize unnecessary conversational filler.
     • Language: Bilingual (English technical terms + formal BM correspondence).
     • Drafting: Always include project code in subject; use standard closing.
     ```

## Consequences
- **Positive**: The AI feels intimately familiar with the Managing Director's unique style and workflow habits over time.
- **Positive**: Complete transparency and control; the user can audit, modify, or erase any personalized preference at any moment.
- **Trade-off**: Requires running a lightweight weekly LLM reflection pass to distill interaction logs into structured preference notes.
