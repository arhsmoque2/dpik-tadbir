# PR-005: Research Report — Caching, Cost Governance & Progressive Context Compression Candidates

**Date**: 2026-08-31
**Target Repository**: `dpik-tadbir`
**Author**: Lead Architecture Agent
**Relationship to PR-004**: Complements, does not replace, [`PR-004-ai-context-compression-and-session-architecture.md`](PR-004-ai-context-compression-and-session-architecture.md). PR-004 scoped rolling-history summarization and large-tool-output offloading against `kabdullah27/php-token-squeezer`, `twdnhfr/laravel-deepagents`, and `hassan-shahriar-1/laravel-chatbot`. This report surveys a **different, non-overlapping set of seven repos** specifically for caching, per-turn cost governance, and repeat-query reuse — the half of "reduce API billing cost" PR-004 didn't cover. See [`ADR-021`](../adr/ADR-021-ai-cost-governance-prompt-caching-and-context-mode-budgets.md) for the decisions drawn from this research and what shipped from it.

---

## 1. Problem space (recap, cost-governance framing)

`AgentService`'s tool loop can call the LLM gateway up to 8 times per executive turn. Before this research, the codebase had **zero token-cost visibility** (every `AiRun` recorded an estimate of the raw user prompt only — not the system prompt, tool schemas, or history actually sent) and **zero caching of any kind** (no repeat-query reuse, no provider-level cache breakpoints). The question this report answers: which parts of that gap does prior art already solve well enough to adapt, and which parts are genuinely unaddressed anywhere in the current PHP/Laravel AI ecosystem.

---

## 2. Per-repo findings

### A. `ykachala/semantic-cache`
- **Repository**: `https://github.com/ykachala/semantic-cache`
- **Core mechanism**: two-tier response cache — Tier 1 is an O(1) SHA-256 exact-hash lookup (`SemanticCache::exactLookup()`); Tier 2 embeds the prompt and cosine-searches a pluggable `VectorStore` (`InMemoryVectorStore`, `Psr16Store`, with `PgVectorStore`/`RedisStore`/`QdrantStore` on the roadmap), returning the cached response above a configurable similarity threshold (default 0.95). Namespace isolation is enforced at the store level (`store->withNamespace()`) so one tenant's cached answer can never surface for another. An in-process single-flight map collapses concurrent misses for the same prompt into one `onMiss()` call.
- **Fit for dpik-tadbir**: Directly answers "return cached response for similar queries" — but only safely for read-only, non-tool-suspended turns (a stale cached "no new mail" answer is a correctness bug once tools/write actions are involved, not a UX nit). Its `HashingEmbedder` is a placeholder; a real embedding call is itself a small added cost per turn, so this only pays off once hit-rate on genuinely repeated phrasing ("check my inbox" vs "any new emails?") is measured to be worth it — sequence after ADR-021's token telemetry, not before.
- **Verdict**: **Phase 3 candidate**, deferred in ADR-021. Needs a real embedding provider wired in and gating logic to exclude any turn that used or could use a write-capable tool.

### B. `magetechsol/laravel-ai-gateway`
- **Repository**: `https://github.com/magetechsol/laravel-ai-gateway`
- **Core mechanism**: a governance layer over the official Laravel AI SDK — `AiQuotaMiddleware` enforces four independent budget tiers (tenant daily-token cap, tenant monthly-$ budget, user daily-request cap, global daily/monthly spend ceiling), each firing an `AiQuotaExceeded` event before throwing. `ModelRouter` resolves a model from named tiers (`fast`/`balanced`/`premium`) in config rather than a single hardcoded default/fallback pair. `AiCacheMiddleware` is a naive exact-match cache keyed on `md5(json_encode($request->input()))` — the raw HTTP body only.
- **Fit for dpik-tadbir**: The quota-tier structure is the most complete of anything surveyed across both PR-004 and this report — dpik-tadbir currently has no quota enforcement of any kind, only the token *visibility* ADR-021 Phase 1 adds. The `ModelRouter` tiering pattern is what ADR-021's `context_mode`-driven budget profiles are directly modeled on (adapted to history-window/max-tokens rather than model selection, since dpik-tadbir's provider fallback is already governed by ADR-002).
- **Verdict**: quota-tier structure is a **Phase 3 candidate** (deferred, needs its own follow-up ADR before implementation — this repo has real-money implications and should get explicit sign-off on limits). `AiCacheMiddleware`'s hashing approach is **rejected** — see `laravel-llm` below for the correct alternative.

### C. `Touseef-khattak/laravel-llm`
- **Repository**: `https://github.com/Touseef-khattak/laravel-llm`
- **Core mechanism**: `ResponseCache::key()` is content-addressed on `$request->fingerprint($driver)` — a hash of *everything that can change the answer* (prompt + model + tools + system prompt), not just the user's raw text. This is the correct cache-key discipline: editing a system prompt or adding a tool misses cleanly instead of serving a stale answer computed under different inputs. Its `PendingRequest`/per-provider `Driver` contract (5 methods: `AnthropicDriver`, `OpenAiDriver`, `GeminiDriver`, `FakeDriver`) is architecturally cleaner than `LlmGatewayService`'s growing if/else, and its `costUsd`/`Usage` (input/output/cache tokens) response fields are exactly the shape ADR-021 Phase 1 now populates on `LlmGatewayService::invokeAnthropic()`'s result.
- **Fit for dpik-tadbir**: The fingerprinting discipline is the one piece worth copying literally, whenever an exact-match cache is built (Phase 3) — do not repeat `laravel-ai-gateway`'s mistake of hashing only the request body. The driver-per-provider refactor is **not** worth doing on its own — `LlmGatewayService` already works and has passing test coverage; rewriting it for architectural cleanliness alone would be pure risk with no functional gain.
- **Verdict**: fingerprinting pattern **adopted by reference** for the Phase 3 exact-match cache design; driver refactor **rejected** (no functional gain, real regression risk).

### D. `Hassan-Shahriar-1/chat-bot-laravel`
*(previously researched in this thread's earlier session — included here for completeness of the "all seven" audit, not re-derived)*
- **Core mechanism**: `ContextWindowManager` folds unsummarized messages beyond a token budget into a running `summary` via a dedicated compaction LLM call; `QuotaManager`/`TokenCounter` (tiktoken-based) enforce daily/monthly token+message quotas for guests and authenticated users.
- **Verdict**: the compaction-prompt pattern is **PR-004's Phase 2 scope** (rolling summarization), not re-scoped here. Guest-quota/HMAC handling **rejected** — no guest surface in this app.

### E. `elliottlawson/converse`
*(previously researched — included for completeness)*
- **Core mechanism**: typed `MessageRole`/`MessageStatus` enums and a first-class `is_complete` boolean instead of a free-form `metadata['status']` string; Eloquent lifecycle events (`MessageCreated`, `MessageCompleted`, etc.); a `MessageChunk` model for streaming (`appendChunk()` + `ChunkReceived` event).
- **Verdict**: **not implemented in this ADR** — genuinely useful (`chat_messages.metadata['status']` should be a real column) but orthogonal to cost governance; tracked as a distinct follow-up, not bundled into ADR-021 to keep that ADR's diff reviewable.

### F. `invokable/laravel-copilot-sdk`
*(previously researched — included for completeness)*
- **Core mechanism**: wraps the GitHub Copilot CLI over RPC, not a general LLM API — not usable as a provider. The one transferable pattern is `HasPermissionHandler` vs `HasToolHandlers`: whether a tool call is allowed to proceed is a **separate, declarative gate**, checked generically, rather than folded into the tool-execution path by name.
- **Verdict**: **adopted in ADR-021** — `BaseTool::requiresConfirmation()` replaces the hardcoded `in_array($name, ['ask_user_question', 'propose_action_card'])` literal that used to live inside `AgentService`'s loop.

### G. `squareetlabs/LaravelToon`
*(previously researched — included for completeness)*
- **Core mechanism**: TOON (Token-Oriented Object Notation) — a compact JSON alternative claiming 30-60%+ token reduction on uniform arrays of objects (exactly the shape of an Outlook message list or project-register result set). A `CompressResponse` HTTP middleware auto-compresses outgoing JSON responses above a size/reduction threshold.
- **Verdict**: the **encoder** is a real Phase 2 candidate for tool-result payloads (`AgentService.php`'s `json_encode($toolResult, ...)` call) — tracked under PR-004's scope (tool-output shaping), not this ADR, since it needs to be paired with field-trimming (drop low-signal fields like raw IDs and full ISO timestamps) to be worth the added encode/decode step, and that shaping work is a larger, separate change. The **middleware** itself doesn't apply — there's no HTTP boundary in the tool-call loop to attach it to; `AgentService` builds tool-result strings in-process.

---

## 3. Comparative matrix

| Dimension | `semantic-cache` | `laravel-ai-gateway` | `laravel-llm` | `dpik-tadbir` (baseline, before this ADR) | `dpik-tadbir` (after ADR-021 Phase 1) |
|---|---|---|---|---|---|
| **Repeat-query caching** | Two-tier exact+semantic | Naive body-hash exact | Content-addressed exact (fingerprint) | None | None (Phase 3) |
| **Prompt caching (provider-native)** | N/A | N/A | N/A | None | Anthropic `cache_control` on system + tools |
| **Token usage telemetry** | N/A | Per-request token tracking | `Usage` DTO (input/output/cache) on every response | Computed by provider, discarded; `strlen/4` estimate persisted instead | Real accumulated `input_tokens`/`output_tokens` across all loop iterations persisted |
| **Quota/budget enforcement** | N/A | 4-tier (tenant tokens/tenant $/user requests/global $) | N/A | None | None (Phase 3, needs its own ADR) |
| **Session/turn budget adjustment** | N/A | Model-tier routing (fast/balanced/premium) | Per-call `.maxTokens()` | Flat 40-message/4096-token constant regardless of session purpose | `context_mode`-driven profile (`inbox_triage`/`drafting`/`project_deepdive`/`general`) |
| **Tool-confirmation gating** | N/A | `ToolAuthorizer` (allowlist-based) | N/A | Hardcoded tool-name literal in the agent loop | Declarative `BaseTool::requiresConfirmation()` |

---

## 4. What shipped vs. deferred

See [ADR-021](../adr/ADR-021-ai-cost-governance-prompt-caching-and-context-mode-budgets.md) §Decision for the authoritative phase breakdown. Summary:

- **Shipped now (Phase 1)**: Anthropic prompt caching, real per-turn token telemetry, `context_mode` token budgets, declarative tool-confirmation gate.
- **Deferred to PR-004's existing scope (Phase 2)**: rolling history summarization/folding, large-tool-result offloading, TOON-style tool-result compression (needs field-shaping first).
- **Deferred, needs a follow-up ADR (Phase 3)**: exact-match response cache (fingerprinted per `laravel-llm`'s pattern), semantic response cache (`semantic-cache`'s two-tier pattern), multi-tier quota/budget enforcement (`laravel-ai-gateway`'s pattern), cache-aware cost pricing in `CostCalculator`.

No candidate repo in this report or PR-004 implements **progressive/tiered compression by relevance** (recent-raw / mid-summarized / old-folded bands) — that remains a genuine gap in the surveyed ecosystem, not something to source externally. If pursued, it is original design work building on PR-004's `ContextWindowManager`/summarization pattern, sequenced after Phase 1's token telemetry gives real data on whether it's worth the complexity.
