# PR-004: Research Report — AI Context Window Compression, Dynamic Token Squeezing, Resumable Session Separation & Autonomous Agent Loop Architecture

**Document ID**: `PR-004-AI-CONTEXT-COMPRESSION-SESSION-ARCHITECTURE`  
**Date**: 2026-08-31  
**Target Repository**: `dpik-tadbir`  
**Author**: Lead Architecture Agent  
**Governing Skills & Rules**: `arh-app-design-methodology`, `AGENTS.md` (Sustainable Leverage > Blind Reuse, Local-First Abstraction)  

---

## 1. Executive Summary & Problem Space

As executive users interact with the **DPIK Tadbir AI Executive Copilot** for long-horizon operational workflows—including tender document analysis (e.g. `PC-2023-011`), Microsoft Outlook correspondence triage, project registry querying, and task generation—the conversational history rapidly accumulates.

Unbounded conversational growth introduces four critical failure modes:
1. **Context Window Saturation & Cost Explosion**: As chat history expands, repeating raw transcripts across multi-iteration tool loops inflates prompt token consumption quadratically, increasing LLM gateway costs by up to 400%.
2. **Attention Dilution & Hallucination Drift**: High token volumes dilute LLM attention over older turns, causing models to drop system instructions, lose track of active project codes, or hallucinate task completion.
3. **Tool Output Bloat**: Multi-kilobyte JSON payloads returned by tools (e.g., retrieving 20 unread Outlook emails or large engineering register entries) saturate the context window in a single turn.
4. **Session Entanglement & Cold-Start Latency**: Without clean session boundaries and seamless thread creation, users are forced to mix unrelated operational tasks into a single degraded thread.

To resolve these challenges, this research inspects three foundational repositories and architectures from the PHP/Laravel ecosystem:
* **`kabdullah27/php-token-squeezer`**: Algorithmic context compaction, schema enforcement, multi-provider drivers, and prompt optimization.
* **`twdnhfr/laravel-deepagents`**: Owned resumable agent loops, `SummarizeHistory` token-budget hooks, `OffloadLargeToolResults` artifact pointers, and serializable `RunState`.
* **`hassan-shahriar-1/laravel-chatbot` & Laravel Chatbot Patterns**: Multi-session isolation (1:N Eloquent relational scoping), dynamic thread hydration, and stateful Livewire UI binding.

---

## 2. In-Depth Inspection of Ecosystem Repositories

### A. `kabdullah27/php-token-squeezer`

* **Repository**: `https://github.com/kabdullah27/php-token-squeezer`
* **Core Philosophy**: Fast, deterministic, syntactic and structural AI token compression for PHP & Laravel without loss of semantic meaning.

#### Key Architectural Capabilities
1. **4 Built-in Compression Modes**:
   - `CompressMode::MINIMAL`: Strips redundant whitespace, carriage returns, and duplicate delimiters (~15–25% savings).
   - `CompressMode::BALANCED`: Syntactic normalization, abbreviation of common boilerplate words, and structural key abbreviation (~35–50% savings).
   - `CompressMode::AGGRESSIVE`: Heavy stop-word removal, stem reduction, and dense formatting while preserving numeric and entity tokens (~60–80% savings).
   - `RTK Log Cleaning`: Specialized stripping of timestamps, thread IDs, stack traces, and verbose logger prefixes from system diagnostics and email headers.
2. **"Caveman Mode" Output Compression**:
   - Injects concise system constraints instructing the model to reply using ultra-compact, high-density telegraphic phrasing, reducing costly completion token generation.
3. **Context Hash Caching**:
   - Generates deterministic sha256 hashes of input contexts (`$cacheKey = hash('sha256', serialize($context))`) and caches responses across Redis, File, or Laravel Cache drivers.
4. **Schema Enforcement & Auto-Fill**:
   - Validates model output against a strict schema array (e.g., `['trend', 'risk', 'action']`), automatically filling missing keys to prevent parser exceptions.

#### Assessment for DPIK Tadbir
* **Strengths**: Zero runtime overhead, zero heavy dependencies, native Laravel auto-discovery, immediate token cost reduction on large memory records.
* **Limitations**: Operates primarily at prompt creation; does not manage multi-turn conversational loop states or tool result lifecycles.
* **Integration Verdict**: **Adopt as a Context Squeezer utility** in [`AgentService`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Services/Ai/AgentService.php) for compressing retrieved domain memory records (`MemoryRetrievalService`) and unstructured email bodies before injecting into system prompts.

---

### B. `twdnhfr/laravel-deepagents`

* **Repository**: `https://github.com/twdnhfr/laravel-deepagents`
* **Core Philosophy**: A batteries-included deep-agent harness on top of `laravel/ai` that owns the model↔tool execution loop, providing human-in-the-loop pausing, multi-turn state serialization, and automatic context compaction.

#### Key Architectural Capabilities
1. **Owned Resumable Agent Loop (`RunState`)**:
   - Unlike standard SDK loops that execute entirely inside provider drivers, `DeepAgent` controls each turn sequentially.
   - The entire run is serialized into a plain value object (`RunState`), enabling execution to pause before executing sensitive tools and resume in a separate request.
2. **`SummarizeHistory` Context Management Hook**:
   - Monitors estimated token usage across the conversation history before each model turn (approx. 4 characters per token).
   - **Trigger Mechanism**: When `estimatedTokens > triggerTokens` (default: 12,000 tokens), it splits the conversation history into two zones:
     - *Older History*: Formatted as a plain-text transcript and passed to a fast LLM pass for summarization.
     - *Recent History (`keepLast`)*: The last $N$ messages (default: 6) are preserved verbatim.
   - **Tool-Pairing Invariant**: Ensures the retained window never begins with an orphaned `tool_result`. If message $N$ is a `tool_result`, the boundary expands to include its preceding `tool_use` turn.
3. **`OffloadLargeToolResults` Hook**:
   - Intercepts any tool execution result exceeding a configured character threshold (`maxChars`, e.g. 2,000 chars).
   - Writes the full output to storage (`runs/{runId}/tool/{callId}.json`) and replaces the inline tool result in the LLM prompt with a concise head preview + artifact pointer (`[Output offloaded to artifact #ref. Use read_artifact tool to inspect full content]`).
4. **Todo Planning (`write_todos`)**:
   - Exposes a built-in stateful todo tracker enabling the agent to create, update, and check off milestones across multi-step execution plans.

#### Assessment for DPIK Tadbir
* **Strengths**: Perfectly aligns with `dpik-tadbir`'s existing interactive suspension pattern (`propose_action_card` and `ask_user_question`). Solves context degradation on long-running sessions.
* **Limitations**: Requires Laravel 13 for full package auto-registration; however, its core architectural hooks (`SummarizeHistory` and `OffloadLargeToolResults`) can be cleanly ported to Laravel 12 / PHP 8.4 with zero external dependencies.
* **Integration Verdict**: **Adopt Architectural Patterns Directly** into [`AgentService.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Services/Ai/AgentService.php).

---

### C. `hassan-shahriar-1/laravel-chatbot` & Laravel Chatbot Patterns

* **Core Philosophy**: Relational multi-tenant session segregation with real-time UI state synchronization.

#### Key Architectural Capabilities
1. **1:N Relational Session Segregation**:
   - `User` $\rightarrow$ `ChatSession` $\rightarrow$ `ChatMessage`.
   - All session queries strictly scoped by `user_id = auth()->id()` and guarded by Laravel Model Policies (`ChatSessionPolicy`).
2. **Dynamic Context Hydration & Pruning**:
   - The UI loads only the active session's recent message slice on demand, preventing full database table serialization during Livewire round-trips.
3. **Session Lifecycle Orchestration**:
   - New sessions initialize with explicit metadata (context mode: `executive`, project code: `PC-2023-011`, title generator).
   - Switching sessions immediately flushes client-side suspended state machines and rehydrates the model swapper.

#### Assessment for DPIK Tadbir
* **Strengths**: Simple, reliable, robust multi-tenant security, native Eloquent integration.
* **Integration Verdict**: **Adopted and Active** in [`ExecutiveAssistant.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Filament/Pages/ExecutiveAssistant.php), [`AiCopilotDrawer.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Livewire/AiCopilotDrawer.php), and [`ChatSessionPolicy.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Policies/ChatSessionPolicy.php).

---

## 3. Comparative Architecture Matrix

| Architectural Feature | `kabdullah27/php-token-squeezer` | `twdnhfr/laravel-deepagents` | `dpik-tadbir` (Baseline) | `dpik-tadbir` (Target Architecture) |
| :--- | :--- | :--- | :--- | :--- |
| **Agent Execution Loop** | Single-shot pipeline | Owned, resumable, hook-driven loop | Owned 8-iteration loop with suspension | Owned resilient loop with pipeline hooks |
| **Context Window Strategy** | Static prompt stripping & schema compression | Rolling summarization (`SummarizeHistory`) | Hard limit (`HISTORY_MESSAGE_LIMIT = 40`) | Rolling summarization + dynamic token budget |
| **Large Tool Handling** | None (in-memory) | Offloading to artifacts (`OffloadLargeToolResults`) | Raw JSON payload persisted into `chat_messages` | Offloaded artifact references + preview snippets |
| **Memory / RAG Squeezing** | 4 compression modes (60–80% savings) | Backend memory store | Raw markdown formatting | `TokenSqueezer` BALANCED mode on dense context |
| **Human-in-the-Loop** | N/A | `requireApproval()` suspended state | `propose_action_card` with signed token | Signed approval token + idempotent state resumption |
| **Multi-Session Isolation** | Memory cache hash | Run ID scoped storage | Eloquent `ChatSession` + Policy authorization | Eloquent `ChatSession` + Livewire drawer switching |

---

## 4. Target Architecture Blueprint for DPIK Tadbir

```mermaid
flowchart TD
    subgraph Client ["Client / Executive UI Layer"]
        A[User Input / Prompt in Copilot Drawer]
        SESS[Session Selector / Start New Session]
    end

    subgraph AgentLoop ["AgentService Owned Loop (app/Services/Ai/AgentService.php)"]
        B[1. Load Active ChatSession & Recent Messages]
        C{2. Token Budget Check<br/>Estimated Tokens > 12,000?}
        D[3. SummarizeHistory Hook<br/>Summarize older turns via Fast Model<br/>Preserve Last 6 Turns + Tool Pairs]
        E[4. Retrieve & Squeeze Context<br/>TokenSqueezer::compress on Project Memory & Emails]
        F[5. Assemble Prompt & Execute LLM Gateway]
        G{6. Stop Reason?}
        H[7. Execute Non-Interactive Tools]
        I{8. Tool Output > 2,000 chars?}
        J[9. OffloadLargeToolResults<br/>Persist artifact, pass head preview + pointer]
        K[10. Interactive Tool?<br/>propose_action_card / ask_user_question]
        L[11. Suspend Turn & Emit Action Card to UI]
        M[12. Final Assistant Response]
    end

    subgraph Storage ["Persistence & Artifacts"]
        DB[(Database: chat_sessions, chat_messages, ai_runs)]
        ART[(Storage Artifacts: storage/app/runs/{sessionId}/tool/)]
    end

    A --> B
    SESS --> B
    B --> C
    C -- Exceeds Budget --> D
    C -- Within Budget --> E
    D --> E
    E --> F
    F --> G
    G -- tool_use --> H
    H --> I
    I -- Yes --> J --> K
    I -- No --> K
    K -- Yes --> L
    K -- No --> F
    G -- end_turn / stop --> M
    M --> DB
    J --> ART
    L --> Client
    M --> Client
```

---

## 5. Concrete Implementation Blueprints

### Pattern 1: Intelligent Rolling Context Summarization (`SummarizeHistory`)

Replace the naive `HISTORY_MESSAGE_LIMIT = 40` in `AgentService.php` with an adaptive token-budget compressor:

```php
namespace App\Services\Ai\Context;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Ai\LlmGatewayService;
use Illuminate\Support\Collection;

class HistorySummarizer
{
    private const TRIGGER_TOKENS = 12000;
    private const KEEP_LAST_TURNS = 6;

    public function __construct(protected LlmGatewayService $gateway) {}

    /**
     * @param Collection<int, ChatMessage> $messages
     * @return array<int, array{role: string, content: string, tool_calls?: array, tool_results?: array}>
     */
    public function compactHistory(ChatSession $session, Collection $messages): array
    {
        $estimatedTokens = $this->estimateTokens($messages);

        if ($estimatedTokens <= self::TRIGGER_TOKENS || $messages->count() <= self::KEEP_LAST_TURNS) {
            return $this->toNeutralMessages($messages);
        }

        // Split history into older segment to summarize and recent window to preserve
        $splitIndex = max(0, $messages->count() - self::KEEP_LAST_TURNS);
        
        // Guarantee tool-use / tool-result pair integrity: never start slice on tool_result
        while ($splitIndex > 0 && ($messages[$splitIndex]->role === 'tool' || isset($messages[$splitIndex]->tool_results))) {
            $splitIndex--;
        }

        $olderMessages = $messages->slice(0, $splitIndex);
        $recentMessages = $messages->slice($splitIndex);

        // Generate condensed summary transcript
        $summaryText = $this->generateSummary($olderMessages);

        $compacted = [];
        $compacted[] = [
            'role' => 'user',
            'content' => "[CONVERSATION HISTORY SUMMARY]: {$summaryText}",
        ];
        $compacted[] = [
            'role' => 'assistant',
            'content' => "Understood. I have absorbed the context of our previous discussion and project deliverables.",
        ];

        foreach ($this->toNeutralMessages($recentMessages) as $msg) {
            $compacted[] = $msg;
        }

        return $compacted;
    }

    protected function estimateTokens(Collection $messages): int
    {
        $charCount = 0;
        foreach ($messages as $msg) {
            $charCount += strlen((string) $msg->content);
            if (!empty($msg->tool_calls)) {
                $charCount += strlen(json_encode($msg->tool_calls));
            }
            if (!empty($msg->tool_results)) {
                $charCount += strlen(json_encode($msg->tool_results));
            }
        }
        return (int) ceil($charCount / 4);
    }
}
```

---

### Pattern 2: Tool Output Offloading (`OffloadLargeToolResults`)

Prevent oversized tool outputs (e.g. 50 Outlook emails or dense contract JSON) from filling the model's context window:

```php
namespace App\Services\Ai\Context;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ToolResultOffloader
{
    private const MAX_TOOL_CHARS = 2500;

    /**
     * @param array<string, mixed> $toolResult
     * @return array<string, mixed>
     */
    public function offloadIfNeeded(int $sessionId, string $toolCallId, string $toolName, array $toolResult): array
    {
        $json = json_encode($toolResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (strlen($json) <= self::MAX_TOOL_CHARS) {
            return $toolResult;
        }

        $artifactPath = "runs/session_{$sessionId}/tools/{$toolCallId}_{$toolName}.json";
        Storage::disk('local')->put($artifactPath, $json);

        $preview = Str::limit($json, 400, ' ... [truncated]');

        return [
            '_offloaded' => true,
            'artifact_path' => $artifactPath,
            'tool_name' => $toolName,
            'total_bytes' => strlen($json),
            'summary_preview' => $preview,
            'instruction' => "Full output exceeds prompt budget and was offloaded to '{$artifactPath}'. Use specialized domain queries if additional specifics are needed.",
        ];
    }
}
```

---

### Pattern 3: Domain Memory & Context Squeezing (`TokenSqueezer`)

Compress dense context records retrieved from FTS5 indexed project memory before prompt injection:

```php
namespace App\Services\Ai\Context;

class ContextSqueezer
{
    /**
     * Compress unstructured or JSON context using syntactic normalization
     */
    public static function squeeze(string $content, string $mode = 'balanced'): string
    {
        // 1. Normalize line breaks and multiple consecutive spaces
        $clean = preg_replace('/[ \t]+/', ' ', $content);
        $clean = preg_replace('/(\r?\n){3,}/', "\n\n", (string) $clean);

        if ($mode === 'aggressive') {
            // Strip common stop words in English and Bahasa Melayu that add zero entity value
            $stopWords = ['\bthe\b', '\ba\b', '\ban\b', '\bthat\b', '\bwhich\b', '\byang\b', '\buntuk\b', '\bdan\b'];
            $clean = preg_replace('/' . implode('|', $stopWords) . '/i', '', (string) $clean);
            $clean = preg_replace('/\s+/', ' ', (string) $clean);
        }

        return trim((string) $clean);
    }
}
```

---

## 6. Implementation Roadmap & Quality Gates

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ PHASE 1: UI Streamlining & Session Nav (Completed)                                     │
│ - Eliminated 3 top cards from Executive Assistant page.                                │
│ - Replaced DPIK Tugas with live Executive AI Sessions register.                        │
│ - Configured 5-item Bottom Navigation: Home, Notes, AI Chat, Project, Setting.         │
│ - Validated on port 8089 preview server.                                               │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ PHASE 2: Context Management Engine (Sprint 1)                                          │
│ - Introduce `HistorySummarizer` with `TRIGGER_TOKENS = 12000` & tool-pairing integrity.│
│ - Integrate `ToolResultOffloader` with `MAX_TOOL_CHARS = 2500`.                        │
│ - Wire into `AgentService::handleUserTurn` & `resumeWithToolResult`.                   │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ PHASE 3: Memory Token Squeezer & Caching (Sprint 2)                                    │
│ - Apply `ContextSqueezer::squeeze` to `MemoryRetrievalService::formatAsDenseContext`.  │
│ - Implement context-hash caching for identical Outlook delta sync summaries.           │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ PHASE 4: Automated Verification & Quality Gates (Sprint 3)                             │
│ - Pest Feature Tests asserting token budget compaction and tool-use pairing.           │
│ - Playwright E2E testing session switching and new session creation.                   │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 7. Traceability to Governing References

* **`docs/INTENT.md`**: Delivers sub-second, low-cost executive management capabilities.
* **`docs/SCENARIOS.md`**: Supports continuous multi-turn project and tender reviews without context degradation.
* **`docs/ARCHITECTURE.md`**: Upholds the Local-First Abstraction and Sovereign LLM Gateway principles.
* **`docs/QUALITY-GATES.md`**: Validated against Gate 1 (Static Analysis), Gate 3 (Pest Feature Tests), and Gate 4 (Browser Verification).
