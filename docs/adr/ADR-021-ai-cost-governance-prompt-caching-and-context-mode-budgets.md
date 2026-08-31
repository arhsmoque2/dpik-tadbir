# ADR-021: AI Cost Governance — Prompt Caching, Real Token Telemetry & Context-Mode Budgets

**Status**: Accepted (Phase 1 implemented; Phases 2-3 deferred — see Consequences)
**Date**: 2026-08-31
**Decision Makers**: Managing Director, Lead Architecture Agent
**Research basis**: [`docs/research/PR-005-caching-cost-governance-and-context-compression-candidates.md`](../research/PR-005-caching-cost-governance-and-context-compression-candidates.md); complements [`docs/research/PR-004-ai-context-compression-and-session-architecture.md`](../research/PR-004-ai-context-compression-and-session-architecture.md) rather than replacing it — PR-004 scoped rolling summarization and tool-output offloading (Phase 2 here); this ADR's Phase 1 is the part that shipped first.

## Context

`AgentService`'s tool-calling loop (`app/Services/Ai/AgentService.php`) can call `LlmGatewayService::complete()` up to `MAX_ITERATIONS` (8) times for a single executive turn. Auditing what each of those calls actually sends turned up four concrete, verifiable problems, none of them requiring new infrastructure to fix:

1. **No Anthropic prompt caching.** The system prompt (rules + live tool catalog + personalization + memory context) and the full tool-schema list are near-identical across every iteration of one turn and across turns in the same session, but `invokeAnthropic()` sent both as plain, uncached payload every time — billing full input-token price on repeats that a `cache_control` breakpoint would serve at roughly 10% of that price on a hit.
2. **Real token usage was computed, then discarded.** `invokeAnthropic()` already returns Anthropic's own `usage.input_tokens`/`usage.output_tokens` from the API response, but `AgentService` never passed them to `AiRunRecorder`. Every `AiRun.cost_usd`/`cost_myr`/`total_tokens` figure was instead a `strlen($prompt) / 4` estimate of the *original user prompt only* — excluding the system prompt, tool schemas, history, and every intermediate completion in a multi-iteration turn. This wasn't just imprecise; it was measuring the wrong thing, and it meant nobody could tell which turns were actually expensive.
3. **`ChatSession.context_mode` was dead data.** The column has existed since the original `chat_sessions` migration (`inbox_triage`/`project_deepdive`/`drafting`/`general`), but every creation site (`AiCopilotDrawer`, `ExecutiveAssistant`, `Dashboard`) hardcodes `'executive'`, and `AgentService` never read the column — every session got an identical 40-message history window and 4096-token output ceiling regardless of what kind of work it was actually doing.
4. **Interactive-tool suspension was a name literal.** `AgentService` decided which tool calls must pause the turn for executive approval via `in_array($toolCall['name'], ['ask_user_question', 'propose_action_card'], true)` — a hardcoded list inside the loop. Any future tool needing the same treatment (e.g. a destructive write, an external-domain email) would require editing `AgentService` itself rather than declaring the requirement on the tool.

PR-005 surveyed seven external Laravel/PHP AI packages specifically for patterns addressing cost governance, caching, and context budgeting; none offered a drop-in fix for the above (most target a generic chatbot shape this codebase has already outgrown — see PR-005 §4 for the full per-repo verdict), but three architectural patterns were worth adapting directly, credited below.

## Decision

### Phase 1 (this ADR, implemented now)

1. **Anthropic prompt caching** (`LlmGatewayService::invokeAnthropic()`): the system prompt is now sent as a single content block with `cache_control: {type: ephemeral}`; the last entry in the `tools` array carries the same breakpoint (a cache breakpoint applies to everything up to and including the marked block, so one marker on the last tool covers the whole catalog). Below Anthropic's per-model minimum cacheable length the field is silently ignored — safe to always set rather than sizing the prompt first.
2. **Real per-turn token telemetry** (`AgentService::handleUserTurn()`): `input_tokens`/`output_tokens` are now accumulated across every loop iteration (each iteration is its own billed call, so the turn's true cost is the sum, not just the last completion's) and passed to `AiRunRecorder::record()` as `prompt_tokens`/`completion_tokens`. The `strlen/4` estimate remains the fallback for providers that don't report usage (OpenRouter's response parsing doesn't currently extract it; the testing-mode mock never did) — this is additive, not a removal of the old fallback path.
3. **`context_mode`-driven budgets** (`AgentService::CONTEXT_MODE_PROFILES` / `contextProfileFor()`): each mode maps to a `{history_limit, max_tokens}` pair — `inbox_triage` (20 messages / 1024 tokens) for quick status checks, `drafting` (30 / 2048) for correspondence, `project_deepdive` (60 / 4096) for research, `general`/`executive` (40 / 4096) reproducing the exact prior constant so no session in production today changes behavior. An unrecognized mode falls back to the `general` profile rather than erroring.
4. **Declarative tool confirmation gate** (`App\Mcp\BaseTool::$requiresConfirmation` / `requiresConfirmation()`): `AskUserQuestionTool` and `ProposeActionCardTool` now declare `requiresConfirmation = true` themselves; `AgentService`'s loop asks the registry (`$this->toolRegistry->get($name)->requiresConfirmation()`) instead of matching a name literal. A future tool opts into suspension by declaring the flag, with no `AgentService` change required. Pattern credited to `invokable/laravel-copilot-sdk`'s `HasPermissionHandler`/`HasToolHandlers` split (PR-005 §3).

### Phase 2 (deferred — tracked in PR-004, not re-scoped here)

Rolling history summarization/folding beyond a raw window, large-tool-result offloading to storage artifacts, and tool-result field-shaping (dropping low-signal fields like internal IDs and full ISO timestamps before a result ever becomes a prompt message) remain PR-004's scope. Nothing in Phase 1 blocks that work; `contextProfileFor()`'s `history_limit` is a hard cutoff exactly like the constant it replaced, not a summarizer.

### Phase 3 (deferred — needs a follow-up ADR before implementation)

- **Exact-match response cache**, content-addressed on prompt+model+system+tools (not just the raw user text, per `Touseef-khattak/laravel-llm`'s `ResponseCache::key()` — PR-005 §2), scoped to read-only, non-tool-suspended turns only, short TTL.
- **Semantic (embedding-similarity) response cache** (`ykachala/semantic-cache` two-tier pattern — PR-005 §1) — needs a real embedding provider wired in first, and careful gating: never applied to a turn that used or would use a write-capable tool, since a stale cached answer for "any new mail?" is a correctness bug, not a UX nit.
- **Precise cache-aware cost accounting**: `LlmGatewayService::invokeAnthropic()` now surfaces `cache_creation_input_tokens`/`cache_read_input_tokens` on its result (Anthropic prices these differently from a normal input token), but `CostCalculator`/`AiRunRecorder` don't yet price them separately — today's `cost_usd`/`cost_myr` will overestimate cost on a cache hit, which is the safe direction but not the accurate one.
- **Multi-tier budget/quota enforcement** (`magetechsol/laravel-ai-gateway`'s `AiQuotaMiddleware` pattern — PR-005 §2: per-user daily tokens, per-executive monthly $ ceiling, global daily/monthly budget) — this repo currently has zero quota enforcement of any kind; Phase 1 only adds *visibility* (real token counts), not *limits*.

## Consequences

- **Positive**: every `AiRun` recorded from this point forward reflects real Anthropic-reported token usage for turns that actually reach the live API, not an estimate of unrelated text — this is the prerequisite for judging whether Phase 2/3 work is worth doing at all.
- **Positive**: repeated system-prompt/tool-schema bytes across a multi-iteration turn (and across turns in one session) are now cache-eligible with zero schema change and no behavior change on a cache miss.
- **Positive**: `context_mode` becomes a real lever instead of dead schema — but nothing in the product actually sets a session's mode to anything other than `'executive'` yet, so this ADR only activates the *mechanism*; choosing when to create a session in `inbox_triage`/`drafting`/`project_deepdive` mode is separate, unstarted product work.
- **Trade-off**: cache-hit savings are not yet reflected in `cost_usd`/`cost_myr` (Phase 3) — the dashboard will under-represent savings until that follow-up lands.
- **Trade-off**: OpenRouter- and mock-routed turns still fall back to the `strlen/4` cost estimate, since neither path currently surfaces real usage figures — only the Anthropic path (the configured default provider) benefits from Phase 1's telemetry accuracy.
- **Negative / risk accepted**: the Anthropic wire format for `system` changed from a plain string to a content-block array (required for `cache_control` to attach at all) — a genuine breaking change to that internal payload shape, covered by an updated assertion in `tests/Feature/Ai/LlmGatewayServiceTest.php` rather than left implicit.
