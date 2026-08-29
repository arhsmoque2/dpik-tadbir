# ADR-002: AI Provider & Model Governance Architecture

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
DPIK Tadbir relies on large language models (LLMs) to power the executive assistant, generate email summaries, synthesize project updates, and draft correspondence. Hardcoding a single AI provider or model creates operational fragility against API rate limits, pricing changes, regional outages, and model deprecation. The system requires full runtime configurability over providers, models, fallback cascades, and context token ceilings.

## Decision
1. **Multi-Provider Adapter Architecture**:
   - Implement a unified `LlmGatewayService` capable of routing requests to **Anthropic Claude**, **Google Gemini**, **OpenAI**, and **Local OpenAI-compatible endpoints (Ollama / vLLM)**.
2. **Expose Full Model & Parameter Configurability in Settings**:
   - **Primary Provider & Model**: Select active provider and model identifier (e.g., `claude-3-5-sonnet-latest`, `gemini-2.5-pro`).
   - **Fallback Provider & Model**: Automatic failover route when primary returns 429/500/503 errors (e.g., `gemini-2.5-flash`).
   - **Inference Hyperparameters**: Runtime sliders for `temperature` (default `0.1` for deterministic accuracy), `top_p`, and `max_output_tokens` (default `4096`).
   - **Context Window Pruning Ceiling**: Configurable token threshold (default `32,000` tokens) that triggers rolling summarization or conversation truncation before reaching hard context limits.
3. **Dynamic System Prompt Templates**:
   - Store the Master Executive Persona Prompt in the database settings store, editable via rich markdown editor with variable interpolation (`{company_name}`, `{executive_name}`, `{current_date}`).

## Consequences
- **Positive**: Zero downtime during vendor outages; switching models requires zero code redeployments.
- **Positive**: Executive tone, persona, and response formatting can be tuned dynamically via the UI.
- **Trade-off**: Requires maintaining multi-provider SDK wrappers and credential management for each supported vendor.
