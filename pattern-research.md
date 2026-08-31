# Pattern Research: AI Context Compression, Session Separation & Context Window Management for DPIK Tadbir

**Document ID**: `PR-004-AI-CONTEXT-COMPRESSION-SESSION-ARCHITECTURE`  
**Date**: 2026-08-31  
**Target Repository**: `dpik-tadbir`  
**Author**: Lead Architecture Agent  
**Governing Skills & Rules**: `arh-app-design-methodology`, `AGENTS.md` (Sustainable Leverage > Blind Reuse, Local-First Abstraction)

---

## 1. Executive Summary & Problem Space

As executive leadership uses **DPIK Tadbir** for multi-step operational workflows (tender analysis, Microsoft Outlook email triage, project registry querying, and task generation), conversational history quickly grows.

Unbounded context growth causes 4 critical failure modes:
1. **Context Window Saturation & Cost Explosion**: Repeating full transcripts across multi-step tool iterations inflates prompt token consumption quadratically.
2. **Attention Dilution & Hallucination Drift**: Large token volumes dilute model attention, causing dropped instructions or hallucinated completions.
3. **Tool Output Bloat**: Multi-kilobyte JSON payloads returned by tools (e.g. retrieving 20 unread Outlook emails) saturate context in a single turn.
4. **Session Entanglement**: Lack of clean session boundaries forces unrelated workflows into a single degraded thread.

---

## 2. In-Depth Inspection of Ecosystem Repositories

### A. `kabdullah27/php-token-squeezer`
* **Repository**: `https://github.com/kabdullah27/php-token-squeezer`
* **Core Mechanisms**:
  * **4 Compression Modes**: `MINIMAL` (whitespace/delimiters, ~20%), `BALANCED` (syntactic normalization, ~40%), `AGGRESSIVE` (stop-word removal, entity preservation, ~60–80%), and `RTK` (system/email log cleaner).
  * **"Caveman Mode"**: System constraints that force ultra-compact, high-density model replies, reducing costly output token usage.
  * **Context-Hash Caching**: Sha256-keyed caching across Redis, File, and Laravel cache stores.
  * **Schema Enforcement**: Enforces structured JSON output keys and automatically fills missing fields.
* **Fit for DPIK Tadbir**: Ideal for compressing retrieved domain memory records (`MemoryRetrievalService`) and raw email bodies before injecting into system prompts.

### B. `twdnhfr/laravel-deepagents`
* **Repository**: `https://github.com/twdnhfr/laravel-deepagents`
* **Core Mechanisms**:
  * **Owned Resumable Agent Loop (`RunState`)**: Controls model↔tool loop turn-by-turn with serializable state objects. Enables human-in-the-loop suspension and resumption.
  * **`SummarizeHistory` Loop Hook**: Evaluates token budget (~4 chars/token). When `estimatedTokens > triggerTokens` (12,000 tokens), older messages are summarized via a fast model pass, while the most recent $N$ turns are kept verbatim.
  * **Tool-Pairing Integrity**: Guarantees retained windows never start on an orphaned `tool_result` by expanding the boundary to include the matching `tool_use`.
  * **`OffloadLargeToolResults` Hook**: Intercepts tool outputs exceeding character thresholds (`maxChars`), persists the full JSON payload to disk/storage artifacts (`runs/{runId}/tool/{callId}.json`), and injects a concise head preview with an artifact pointer into the prompt.
* **Fit for DPIK Tadbir**: Directly maps to our interactive suspension model (`propose_action_card`, `ask_user_question`) and provides the exact rolling history compression and tool offloading architecture needed for `AgentService.php`.

### C. `hassan-shahriar-1/laravel-chatbot` & Laravel Chatbot Patterns
* **Core Mechanisms**:
  * **1:N Relational Session Segregation**: `User` $\rightarrow$ `ChatSession` $\rightarrow$ `ChatMessage` guarded by `ChatSessionPolicy`.
  * **Dynamic Context Hydration**: Only loading the active session's recent message slice into Livewire/memory.
  * **Session Lifecycle**: Explicit metadata initialization, title generation, switching, and thread cleanup.
* **Fit for DPIK Tadbir**: Implemented and active across [`ExecutiveAssistant.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Filament/Pages/ExecutiveAssistant.php), [`AiCopilotDrawer.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Livewire/AiCopilotDrawer.php), and the simplified UI.

---

## 3. Comparative Architecture Matrix

| Architectural Dimension | `kabdullah27/php-token-squeezer` | `twdnhfr/laravel-deepagents` | `dpik-tadbir` (Current Baseline) | `dpik-tadbir` (Target Architecture) |
| :--- | :--- | :--- | :--- | :--- |
| **Agent Execution Loop** | Single-shot pipeline | Owned, resumable, hook-driven loop | Owned 8-iteration loop with suspension | Owned resilient loop with pipeline hooks |
| **Context Window Strategy** | Static prompt stripping & schema compression | Rolling summarization (`SummarizeHistory`) | Hard limit (`HISTORY_MESSAGE_LIMIT = 40`) | Rolling summarization + dynamic token budget |
| **Large Tool Handling** | None (in-memory) | Offloading to artifacts (`OffloadLargeToolResults`) | Raw JSON payload persisted into `chat_messages` | Offloaded artifact references + preview snippets |
| **Memory / RAG Squeezing** | 4 compression modes (60–80% savings) | Backend memory store | Raw markdown formatting | `TokenSqueezer` BALANCED mode on dense context |
| **Human-in-the-Loop** | N/A | `requireApproval()` suspended state | `propose_action_card` with signed token | Signed approval token + idempotent state resumption |
| **Multi-Session Isolation** | Memory cache hash | Run ID scoped storage | Eloquent `ChatSession` + Policy authorization | Eloquent `ChatSession` + Livewire drawer switching |

---

## 4. Architecture Data Flow & Lifecycle

```mermaid
flowchart TD
    subgraph Client ["Client / Executive UI Layer"]
        A[User Input in Copilot Drawer]
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

## 5. Ready-Made Implementation Code Blueprints

### 1. Rolling Context Summarization (`HistorySummarizer`)
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

    public function compactHistory(ChatSession $session, Collection $messages): array
    {
        $estimatedTokens = $this->estimateTokens($messages);

        if ($estimatedTokens <= self::TRIGGER_TOKENS || $messages->count() <= self::KEEP_LAST_TURNS) {
            return $this->toNeutralMessages($messages);
        }

        $splitIndex = max(0, $messages->count() - self::KEEP_LAST_TURNS);
        while ($splitIndex > 0 && ($messages[$splitIndex]->role === 'tool' || isset($messages[$splitIndex]->tool_results))) {
            $splitIndex--;
        }

        $olderMessages = $messages->slice(0, $splitIndex);
        $recentMessages = $messages->slice($splitIndex);

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

### 2. Large Tool Output Offloading (`ToolResultOffloader`)
```php
namespace App\Services\Ai\Context;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ToolResultOffloader
{
    private const MAX_TOOL_CHARS = 2500;

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

### 3. Context & Dense Memory Squeezing (`ContextSqueezer`)
```php
namespace App\Services\Ai\Context;

class ContextSqueezer
{
    public static function squeeze(string $content, string $mode = 'balanced'): string
    {
        $clean = preg_replace('/[ \t]+/', ' ', $content);
        $clean = preg_replace('/(\r?\n){3,}/', "\n\n", (string) $clean);

        if ($mode === 'aggressive') {
            $stopWords = ['\bthe\b', '\ba\b', '\ban\b', '\bthat\b', '\bwhich\b', '\byang\b', '\buntuk\b', '\bdan\b'];
            $clean = preg_replace('/' . implode('|', $stopWords) . '/i', '', (string) $clean);
            $clean = preg_replace('/\s+/', ' ', (string) $clean);
        }

        return trim((string) $clean);
    }
}
```

---

## 6. Implementation Status & Rollout

1. **Phase 1 (Completed)**:
   - Simplified Executive Assistant view: removed top 3 preset cards.
   - Replaced tasks with live `Executive AI Sessions` register with Resume/Delete/New Session actions.
   - Configured 5-item Bottom Navigation (`Home`, `Notes`, `AI Chat`, `Project`, `Setting`).
   - Verified live preview on port 8089.
2. **Phase 2 (Next)**:
   - Wire `HistorySummarizer` and `ToolResultOffloader` into [`AgentService.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Services/Ai/AgentService.php).
   - Apply `ContextSqueezer` to [`MemoryRetrievalService.php`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/app/Services/Memory/MemoryRetrievalService.php).
