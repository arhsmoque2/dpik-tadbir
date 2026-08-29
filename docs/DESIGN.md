# DPIK Tadbir: System Design Specification

## [DESIGN-01] Component Breakdown & Subsystem Architecture
DPIK Tadbir is structured as a full-stack Laravel 12 application with a Filament v4 control plane. The system is partitioned into 8 decoupled subsystems:

```text
┌────────────────────────────────────────────────────────────────────────────┐
│                             DPIK TADBIR WORKSTATION                        │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  [1. Executive Control Plane & Filament UI]                                │
│  • App\Filament\Pages\ExecutiveAssistant (Livewire Chat Drawer & Presets)  │
│  • App\Filament\Resources\ProjectRegisterResource (Continuous Domain Mem)  │
│  • App\Filament\Resources\ActivityRollupResource (Daily/Weekly Audit Logs) │
│  • App\Filament\Pages\ExecutiveSettings (Namespaced Settings Control)     │
│                                                                            │
│  [2. AI Agent Core & State Machine]                                        │
│  • App\Services\Ai\AgentService (Multi-Turn Conversational Reasoning Loop) │
│  • App\Services\Ai\LlmGatewayService (Multi-Provider Anthropic/Gemini/OAI) │
│  • App\Services\Ai\AntiHallucinationGuard (Action Verification Validator)  │
│  • App\Services\Ai\PersonalizationReflectionService (Weekly Profiler)      │
│                                                                            │
│  [3. MCP Bridge & Interactive Tool Registry]                               │
│  • App\Mcp\ToolRegistry (laravel/mcp Adapter & Permission Scoper)          │
│  • App\Mcp\Tools\Outlook\* (Search, DeltaScan, ReadMessage, Draft, Reply)  │
│  • App\Mcp\Tools\Interactive\* (AskUserQuestion, ProposeActionCard)        │
│  • App\Mcp\Tools\Memory\* (QueryProjectRegister, CommitProjectRegister)    │
│  • App\Mcp\Tools\Projects\* (ListProjects, ReassignTicket)                 │
│  • App\Mcp\Tools\Staff\* (GetStaffWorkload)                                │
│  • App\Services\Mcp\OutlookMcpBridge (Stdio/IPC connector to Python server)│
│                                                                            │
│  [4. Project Register & Hybrid Retrieval Subsystem (ARH Session Reader)]   │
│  • App\Services\Memory\MemoryRetrievalService (FTS5 BM25 + RRF Reranker)   │
│  • App\Services\Memory\DecisionMarkerExtractor (dm:decision/commitment)    │
│  • App\Services\Memory\DenseContextFormatter (Token-Dense Pipe Context)    │
│  • App\Models\ProjectRegistryEntry & DB Triggers for FTS5 Virtual Table    │
│                                                                            │
│  [5. Action Memory & Executive Rollups Engine]                             │
│  • App\Services\Audit\ActionMemoryService (Receipt Logger & Audit Trail)   │
│  • App\Jobs\GenerateDailyRollupJob & GenerateWeeklyRollupJob               │
│  • App\Models\AiActionReceipt & AuditLog                                   │
│                                                                            │
│  [6. Runtime Settings & Preset Engine]                                     │
│  • App\Settings\* (AiSettings, OutlookSettings, SafetySettings, etc.)      │
│  • App\Services\Presets\PresetExecutionService (Dynamic Prompt Template)   │
│  • App\Models\ExecutivePreset                                              │
│                                                                            │
│  [7. Project & Staff Oversight Engine (CAP-008)]                           │
│  • App\Services\Projects\ProjectOversightService (Delivery & Health Check) │
│  • App\Services\Staff\StaffWorkloadService (Capacity & Bottleneck Audit)   │
│  • App\Models\Department, Position, PositionAssignment, Project, Ticket    │
│                                                                            │
│  [8. Visual Command Center & Multi-Role RBAC (CAP-009, CAP-010)]           │
│  • App\Filament\Widgets\ExecutiveStatsOverview, PendingActionCardsWidget   │
│  • App\Filament\Widgets\ProjectHealthBoard, RecentActivityRollupWidget     │
│  • App\Policies\PersonalNotePolicy, PersonalTaskPolicy, ProjectPolicy      │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## [DESIGN-02] State Authority & Data Ownership

| Data Domain | Canonical State Authority | Local Storage Model | Cache & Invalidation Policy |
| :--- | :--- | :--- | :--- |
| **Raw Outlook Emails** | **Microsoft Exchange / Graph API** | **Zero raw storage** (ephemeral context only) | Transient prompt window; discarded after turn |
| **Project Knowledge** | `project_registry_entries` table | Relational + SQLite FTS5 Virtual Table | Synchronized via DB triggers on save |
| **User Personalization** | `user_personalization_profiles` | JSON column / Model | In-memory cache; flushed on weekly reflection / save |
| **Action Receipts** | `ai_action_receipts` | Immutable Append-Only Ledger | Indexed by `user_id` and `executed_at` |
| **Runtime Settings** | `system_settings` (Spatie) | Database-backed Key-Value Store | In-memory cache; hot-reloaded on Filament save |
| **Executive Presets** | `executive_presets` table | Relational Eloquent Model | Cached in Database/Array/File cache (TTL: 24h) |
| **Personal Notes/Tasks**| `personal_notes`, `personal_tasks` | User-scoped Relational Tables | Scoped strictly via `PersonalNotePolicy`, `PersonalTaskPolicy` |
| **Projects & Epics** | `projects`, `epics`, `tickets` | Relational Store | Scoped via `ProjectPolicy`, `TicketPolicy` (Role-gated) |
| **Organization & Staff**| `departments`, `positions`, `position_assignments` | Relational Store | Scoped via HR / Executive policies |

---

## [DESIGN-03] Concrete Class Contracts & Method Signatures

### 1. AI Agent Loop & Provider Gateway
```php
namespace App\Services\Ai;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\DTOs\AiTurnResponse;

class AgentService
{
    public function __construct(
        protected LlmGatewayService $llmGateway,
        protected ToolRegistry $toolRegistry,
        protected AntiHallucinationGuard $guard,
        protected MemoryRetrievalService $memory
    ) {}

    public function handleUserTurn(ChatSession $session, string $prompt): AiTurnResponse;
    public function resumeWithToolResult(ChatSession $session, string $toolUseId, array $result): AiTurnResponse;
}

class LlmGatewayService
{
    public function complete(array $messages, array $tools = [], array $options = []): array;
    public function getActiveProvider(): string;
    public function getActiveModel(): string;
}

class AntiHallucinationGuard
{
    /**
     * Validates that any action claimed by the LLM response is backed by an actual AiActionReceipt in the current turn.
     */
    public function validateTurnResponse(AiTurnResponse $response, ChatSession $session): bool;
}
```

### 2. Outlook MCP Bridge Client
```php
namespace App\Services\Mcp;

class OutlookMcpBridge
{
    public function callTool(string $toolName, array $arguments = []): array;
    public function checkAuthStatus(): bool;
    public function fetchInboxDelta(int $lookbackHours = 24, int $limit = 25, bool $concise = true): array;
    public function searchMail(string $query, int $limit = 25, bool $concise = true): array;
    public function readMessage(string $messageId, bool $concise = true): array;
    public function createDraft(string $subject, string $body, array $toRecipients, array $ccRecipients = []): array;
    public function sendReply(string $messageId, string $body, array $attachments = []): bool;
    public function forwardMessage(string $messageId, array $toRecipients, string $comment): bool;
}
```

### 3. Unified MCP Tool Classes & Hierarchy
All tool classes inherit from `Laravel\Mcp\Server\Tool` and implement standardized schema contracts:

```php
namespace App\Mcp\Tools\Outlook;

use Laravel\Mcp\Server\Tool;
use App\Services\Mcp\OutlookMcpBridge;

class OutlookCreateDraftTool extends Tool
{
    protected string $name = 'outlook_create_draft';
    protected string $description = 'Stages a new email draft in Outlook via Microsoft Graph API.';
    public function schema(): array;
    public function handle(array $arguments): array;
}

class OutlookReplyTool extends Tool
{
    protected string $name = 'outlook_reply';
    protected string $description = 'Dispatches a contextual reply to an existing Outlook thread. Requires human confirmation.';
    public function schema(): array;
    public function handle(array $arguments): array;
}

class OutlookForwardTool extends Tool
{
    protected string $name = 'outlook_forward';
    protected string $description = 'Forwards an existing Outlook email message to specified recipients. Requires human confirmation.';
    public function schema(): array;
    public function handle(array $arguments): array;
}

class OutlookSearchMailTool extends Tool
{
    protected string $name = 'outlook_search_mail';
    protected string $description = 'Searches Outlook mailbox with concise executive mode.';
    public function schema(): array;
    public function handle(array $arguments): array;
}

class OutlookListInboxDeltaTool extends Tool
{
    protected string $name = 'outlook_list_inbox_delta';
    protected string $description = 'Fetches new or unread emails since lookback horizon.';
    public function schema(): array;
    public function handle(array $arguments): array;
}

class OutlookReadMessageTool extends Tool
{
    protected string $name = 'outlook_read_message';
    protected string $description = 'Reads full message contents and attachment metadata by ID.';
    public function schema(): array;
    public function handle(array $arguments): array;
}
```

### 4. Hybrid Memory Retrieval & FTS5 Subsystem (ARH Session Reader Pattern)
```php
namespace App\Services\Memory;

use Illuminate\Support\Collection;
use App\DTOs\MemorySearchResult;

class MemoryRetrievalService
{
    /**
     * Executes dual-path lexical FTS5 BM25 + Recency RRF Search.
     */
    public function search(
        string $query,
        ?string $projectCode = null,
        ?string $since = '30d',
        ?string $decisionMarker = null,
        int $limit = 10
    ): Collection;

    public function formatAsDenseContext(Collection $results): string;
}

class DenseContextFormatter
{
    /**
     * Emits token-dense format: "YYYY-MM-DD | project:CODE | dm:TYPE | snippet"
     */
    public function format(Collection $records): string;
}
```

### 5. Project & Staff Oversight Services (CAP-008)
```php
namespace App\Services\Projects;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectOversightService
{
    public function getProjectHealthSummary(Project $project): array;
    public function getActiveEpics(Project $project): Collection;
    public function getOverdueTickets(?int $departmentId = null): Collection;
}

namespace App\Services\Staff;

use App\Models\User;
use Illuminate\Support\Collection;

class StaffWorkloadService
{
    public function getStaffCapacity(User $user): array;
    public function getDepartmentWorkloadOverview(int $departmentId): Collection;
    public function detectDeliveryBottlenecks(): Collection;
}

namespace App\Mcp\Tools\Projects;

use Laravel\Mcp\Server\Tool;

class ReassignTicketTool extends Tool
{
    protected string $name = 'reassign_ticket';
    protected string $description = 'Reassigns a project ticket to a new staff member with audit rationale. Requires confirmation.';
    public function schema(): array;
    public function handle(array $arguments): array;
}
```

### 6. Interactive Human-in-the-Loop Tools
```php
namespace App\Mcp\Tools\Interactive;

use Laravel\Mcp\Server\Tool;

class AskUserQuestionTool extends Tool
{
    protected string $name = 'ask_user_question';
    protected string $description = 'Presents a multiple-choice question modal to the executive with non-exclusive freeform notes and escape hatches (Skip/Cancel).';

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['question', 'options'],
            'properties' => [
                'question' => ['type' => 'string'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                'is_multi_select' => ['type' => 'boolean', 'default' => false],
                'allow_custom_input' => ['type' => 'boolean', 'default' => true],
                'custom_input_placeholder' => ['type' => 'string']
            ]
        ];
    }

    public function handle(array $arguments): array
    {
        return ['status' => 'suspended', 'state' => 'AWAITING_USER_INPUT', 'payload' => $arguments];
    }
}

class ProposeActionCardTool extends Tool
{
    protected string $name = 'propose_action_card';
    protected string $description = 'Stages an actionable proposal (email draft, reply, forward, ticket reassignment) requiring human confirmation.';

    public function schema(): array;
    public function handle(array $arguments): array;
}
```

### 7. Multi-Role Authorization & Security Policies (CAP-010 / RBAC)
```php
namespace App\Policies;

use App\Models\User;
use App\Models\PersonalNote;
use App\Models\PersonalTask;
use App\Models\Project;
use App\Models\Ticket;

class PersonalNotePolicy
{
    public function view(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }
    public function update(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }
    public function delete(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }
}

class PersonalTaskPolicy
{
    public function view(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }
    public function update(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }
}

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager', 'staff']);
    }
    public function update(User $user, Project $project): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager']);
    }
}

class TicketPolicy
{
    public function reassign(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager']);
    }
}
```

---

## [DESIGN-04] Asynchronous Interactive State Machine

```mermaid
stateDiagram-v2
    [*] --> IDLE
    IDLE --> PROCESSING: User submits prompt or clicks preset
    
    state PROCESSING {
        [*] --> LLM_INFERENCE
        LLM_INFERENCE --> EVALUATE_TOOL_CALL
        EVALUATE_TOOL_CALL --> EXECUTE_READ_TOOL: Read Tool (FTS5 / Delta Scan / Workload)
        EXECUTE_READ_TOOL --> LLM_INFERENCE: Tool Result Returned
        
        EVALUATE_TOOL_CALL --> SUSPEND_INTERACTIVE: Interactive Tool Call (AskQuestion / ProposeCard)
    }

    SUSPEND_INTERACTIVE --> AWAITING_USER_INPUT: Emit Livewire Event
    
    state AWAITING_USER_INPUT {
        [*] --> RENDER_MODAL_OR_CARD
        RENDER_MODAL_OR_CARD --> USER_SELECTION: Select Option + Notes
        RENDER_MODAL_OR_CARD --> USER_SKIP: Click [Skip]
        RENDER_MODAL_OR_CARD --> USER_CANCEL: Click [Cancel]
    }

    USER_SELECTION --> RESUMING: Livewire emits resumeWithToolResult
    USER_SKIP --> RESUMING: Livewire emits action=skipped
    USER_CANCEL --> IDLE: Session cancelled

    state RESUMING {
        [*] --> INJECT_TOOL_RESULT
        INJECT_TOOL_RESULT --> EXECUTE_DISPATCH: If Action Card Approved
        EXECUTE_DISPATCH --> LOG_RECEIPT: Write to ai_action_receipts
        LOG_RECEIPT --> LLM_FINAL_RESPONSE: Stream completion
    }

    RESUMING --> IDLE: Turn Complete & Stream Finished
```

---

## [DESIGN-05] Error Handling, Degradation & Safety Guardrails

1. **LLM Primary Failure & Failover Cascade**:
   - If the primary provider (e.g. Anthropic) returns `429 Too Many Requests` or `503 Service Unavailable`, `LlmGatewayService` automatically retries against the configured fallback provider (e.g. Google Gemini) with exponential backoff ($250\text{ms} \rightarrow 1\text{s} \rightarrow 2\text{s}$).
2. **Outlook MCP Disconnection Graceful Recovery**:
   - If the Python bridge fails or Graph token expires (401), the UI renders an inline danger badge with an actionable **[Re-authenticate Outlook]** button rather than crashing the Filament view.
3. **Anti-Hallucination Guard (`AntiHallucinationGuard`)**:
   - Any response where the assistant claims to have sent an email, created a note, or reassigned a ticket without an associated `AiActionReceipt` in the current turn is rejected and re-prompted.
4. **Context Window Token Overflow Protection**:
   - Injected memory from FTS5 is strictly hard-capped by `MemoryTokenCeiling` (default `1,500` tokens). If candidate records exceed the ceiling, the RRF ranker prunes the lowest-scoring records automatically.
5. **Fail-Closed Write Confirmation Tokens**:
   - Every mutation payload dispatched through `OutlookCreateDraftTool`, `OutlookReplyTool`, `OutlookForwardTool`, or `ReassignTicketTool` requires a cryptographically signed one-time token generated by `ProposeActionCardTool`. Missing or forged tokens trigger an immediate 403 exception.
