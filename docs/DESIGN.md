# DPIK Tadbir: System Design Specification

## [DESIGN-01] Component Breakdown & Subsystem Architecture
DPIK Tadbir is structured as a full-stack Laravel 12 application with a Filament v4 control plane. The system is partitioned into decoupled subsystems, supporting whitelisted multi-executive access ([`ADR-013`](adr/ADR-013-whitelisted-registration-and-sovereign-executive-isolation.md)) while keeping employee ticketing deferred ([`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md)):

```text
┌────────────────────────────────────────────────────────────────────────────┐
│                             DPIK TADBIR WORKSTATION                        │
│             (Whitelisted Multi-Executive Sovereign Workspaces)             │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│  [1. Executive Control Plane & Filament UI]                                │
│  • App\Filament\Pages\ExecutiveAssistant (Livewire Chat Drawer & Presets)  │
│  • App\Filament\Resources\ProjectRegisterResource (Continuous Domain Mem)  │
│  • App\Filament\Resources\ActivityRollupResource (Daily/Weekly Audit Logs) │
│  • App\Filament\Pages\ExecutiveSettings (Per-User Settings & Outlook Auth) │
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
│  • App\Mcp\Tools\Notes\* (CreatePersonalNote, CreatePersonalTask)          │
│  • App\Services\Mail\MailBridge (Per-user IMAP/SMTP mailbox bridge)       │
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
│  • App\Models\ExecutivePreset (User-scoped with system default seeds)      │
│                                                                            │
│  [7. Whitelisted Auth & Sovereign Workspace Provisioning (ADR-013)]       │
│  • App\Services\Auth\RegistrationWhitelistService (Email Whitelist Guard)  │
│  • App\Http\Middleware\RegistrationWhitelistMiddleware                     │
│  • App\Models\AllowedRegistrationEmail                                     │
│                                                                            │
│  [8. Project & Staff Oversight Engine (Deferred per ADR-012)]              │
│  • App\Services\Projects\ProjectOversightService (Phase 2 Resumption)      │
│  • App\Services\Staff\StaffWorkloadService (Phase 2 Resumption)            │
│  • App\Models\Department, Position, PositionAssignment, Project, Ticket    │
│                                                                            │
│  [9. Visual Command Center & MCP Access Boundary (CAP-009, CAP-010)]       │
│  • App\Filament\Widgets\ExecutiveStatsOverview, PendingActionCardsWidget   │
│  • App\Filament\Widgets\RecentActivityRollupWidget                         │
│  • App\Policies\PersonalNotePolicy, PersonalTaskPolicy                     │
│  • External MCP Token Authentication Middleware (`/mcp` endpoint)          │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

---

## [DESIGN-02] State Authority, Data Ownership & Scoping

| Data Domain | Canonical State Authority | Local Storage Model | Scoping & Cache Invalidation Policy |
| :--- | :--- | :--- | :--- |
| **Registration Whitelist** | `allowed_registration_emails` | Relational Eloquent Model | Cached in Database/Memory; managed by super admin |
| **Raw Outlook Emails** | **Microsoft Exchange / Graph API** | **Zero raw storage** (ephemeral context only) | Transient prompt window; discarded after turn |
| **Project Knowledge** | `project_registry_entries` table | Relational + SQLite FTS5 Virtual Table | Synchronized via DB triggers; shared company-wide |
| **User Personalization** | `user_personalization_profiles` | JSON column / Model | In-memory cache; flushed on weekly reflection / save |
| **Action Receipts** | `ai_action_receipts` | Immutable Append-Only Ledger | Scoped strictly per `user_id` and `executed_at` |
| **Runtime Settings** | `system_settings` (Spatie) | Database-backed Key-Value Store | In-memory cache; hot-reloaded on Filament save |
| **Executive Presets** | `executive_presets` table | Relational Eloquent Model | User-scoped (`user_id` nullable for system seeds; owned by `auth()->id()`) |
| **Personal Notes/Tasks**| `personal_notes`, `personal_tasks` | User-scoped Relational Tables | Scoped strictly to `auth()->id()` via `PersonalNotePolicy`, `PersonalTaskPolicy` |
| **Chat Sessions/Messages**| `chat_sessions`, `chat_messages` | User-scoped Relational Tables | Scoped strictly to `auth()->id()` |
| **Projects & Epics** *(Deferred)* | `projects`, `epics`, `tickets` | Relational Store | Deferred per [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md) |
| **Staff & Organization** *(Deferred)*| `departments`, `positions`, `position_assignments` | Relational Store | Deferred per [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md) |

---

## [DESIGN-03] Domain Model Taxonomy & Definitions

### 1. The Malaysian Engineering Domain Split
- **"Tugas"**: The overarching product and marketing category term signaling Malaysian engineering workflows ([`OPP-001`](OPPORTUNITIES.md)). Codebase internals remain in plain English (`actions`, `deliverables`, `personal_tasks`).
- **Action / Aksi**: Fast-track operational tasks (emails, queries, site checks) modeled as `PersonalTask` / `actions`.
- **Deliverable**: Milestone-driven engineering technical documents (Reports, BQ, Tender Drawings) with formal revision lifecycles ($R_0, R_1, R_2, \dots$) tied to fee claims (Phase 2).
- **PIC (Person-In-Charge)**: Statutory/governance accountability role (PE / Lead Consultant sign-off), distinct from the operational assignee.

### 2. Nominal Capacity Definition
**Nominal Capacity** is defined as the baseline volume capacity index ($1.0 = 100\%$ allocated baseline volume across active Action, Deliverable, and PIC assignments for an engineer).
- *Policy Guardrail ([`OPP-002`](OPPORTUNITIES.md))*: Active algorithmic capacity scoring is deferred per [`ADR-012`](adr/ADR-012-scope-reduction-defer-project-staff-oversight.md). Volume allocation metrics are strictly observational; the system never auto-evaluates performance or recommends personnel actions.

---

## [DESIGN-04] Concrete Class Contracts & Method Signatures

### 1. Whitelisted Registration Service & Guard
```php
namespace App\Services\Auth;

use App\Models\AllowedRegistrationEmail;
use App\Models\User;

class RegistrationWhitelistService
{
    public function isEmailAllowed(string $email): bool;
    public function whitelistEmail(string $email, string $notes, ?User $byUser = null): AllowedRegistrationEmail;
    public function revokeEmail(string $email): bool;
}

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationWhitelistMiddleware
{
    public function handle(Request $request, Closure $next): Response;
}
```

### 2. AI Agent Loop & Provider Gateway
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

### 3. Outlook MCP Bridge Client
```php
namespace App\Services\Mcp;

use App\Models\User;

class OutlookMcpBridge
{
    public function forUser(User $user): self;
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

### 4. Unified MCP Tool Classes & Hierarchy
All tool classes inherit from `Laravel\Mcp\Server\Tool` and implement standardized schema contracts:

```php
namespace App\Mcp\Tools\Outlook;

use Laravel\Mcp\Server\Tool;
use App\Services\Mail\MailBridge;

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

### 5. Hybrid Memory Retrieval & FTS5 Subsystem (ARH Session Reader Pattern)
```php
namespace App\Services\Memory;

use Illuminate\Support\Collection;
use App\DTOs\MemorySearchResult;

class MemoryRetrievalService
{
    /**
     * Executes dual-path lexical FTS5 BM25 + Recency RRF Search across company Project Register.
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
    protected string $description = 'Stages an actionable proposal (email draft, reply, forward) requiring human confirmation.';

    public function schema(): array;
    public function handle(array $arguments): array;
}
```

### 7. Authorization & Sovereign Personal Isolation Policies
```php
namespace App\Policies;

use App\Models\User;
use App\Models\PersonalNote;
use App\Models\PersonalTask;
use App\Models\ExecutivePreset;
use App\Models\ChatSession;
use App\Models\Project;
use App\Models\Ticket;

class PersonalNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }
    public function create(User $user): bool
    {
        return true;
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
    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }
    public function create(User $user): bool
    {
        return true;
    }
    public function update(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }
    public function delete(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }
}

class ExecutivePresetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, ExecutivePreset $preset): bool
    {
        return $preset->user_id === null || $preset->user_id === $user->id;
    }
    public function update(User $user, ExecutivePreset $preset): bool
    {
        return $preset->user_id === $user->id || $user->role === 'super_admin';
    }
    public function create(User $user): bool
    {
        return true;
    }
    public function delete(User $user, ExecutivePreset $preset): bool
    {
        // System seeds (user_id === null) are only removable by the super admin.
        return $preset->user_id === $user->id
            || ($preset->user_id === null && $user->role === 'super_admin');
    }
}

class ChatSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, ChatSession $session): bool
    {
        return $user->id === $session->user_id;
    }
    public function update(User $user, ChatSession $session): bool
    {
        return $user->id === $session->user_id;
    }
    public function delete(User $user, ChatSession $session): bool
    {
        return $user->id === $session->user_id;
    }
}

class AllowedRegistrationEmailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'super_admin';
    }
    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }
    public function delete(User $user, AllowedRegistrationEmail $email): bool
    {
        return $user->role === 'super_admin';
    }
}

/**
 * Deferred Policies (Preserved for Phase 2 Resumption per ADR-012)
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager', 'staff']);
    }
    public function view(User $user, Project $project): bool
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
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager', 'staff']);
    }
    public function view(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager', 'staff']);
    }
    public function reassign(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, ['super_admin', 'managing_director', 'admin', 'project_manager']);
    }
}
```

---

## [DESIGN-05] Asynchronous Interactive State Machine

```mermaid
stateDiagram-v2
    [*] --> IDLE
    IDLE --> PROCESSING: User submits prompt or clicks preset
    
    state PROCESSING {
        [*] --> LLM_INFERENCE
        LLM_INFERENCE --> EVALUATE_TOOL_CALL
        EVALUATE_TOOL_CALL --> EXECUTE_READ_TOOL: Read Tool (FTS5 / Delta Scan / Memory)
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

## [DESIGN-06] Error Handling, Degradation & Safety Guardrails

1. **LLM Primary Failure & Failover Cascade**:
   - If the primary provider (e.g. Anthropic) returns `429 Too Many Requests` or `503 Service Unavailable`, `LlmGatewayService` automatically retries against the configured fallback provider (e.g. Google Gemini) with exponential backoff ($250\text{ms} \rightarrow 1\text{s} \rightarrow 2\text{s}$).
2. **Outlook MCP Disconnection Graceful Recovery**:
   - If the Python bridge fails or Graph token expires (401), the UI renders an inline danger badge with an actionable **[Re-authenticate Outlook]** button rather than crashing the Filament view.
3. **Registration Whitelist Guard**:
   - Registration attempts from non-whitelisted emails are halted before model instantiation and log a security audit event.
4. **Anti-Hallucination Guard (`AntiHallucinationGuard`)**:
   - Any response where the assistant claims to have sent an email, created a note, or saved a register update without an associated `AiActionReceipt` in the current turn is rejected and re-prompted.
5. **Context Window Token Overflow Protection**:
   - Injected memory from FTS5 is strictly hard-capped by `MemoryTokenCeiling` (default `1,500` tokens). If candidate records exceed the ceiling, the RRF ranker prunes the lowest-scoring records automatically.
6. **Fail-Closed Write Confirmation Tokens**:
   - Every mutation payload dispatched through `OutlookCreateDraftTool`, `OutlookReplyTool`, or `OutlookForwardTool` requires a cryptographically signed one-time token generated by `ProposeActionCardTool`. Missing or forged tokens trigger an immediate 403 exception.

---

## [DESIGN-07] Filament v4 Component Mapping & Build-vs-Buy Boundary

Every surface described in [`UI.md`](UI.md) maps to a concrete Filament v4 construct **before** any custom Livewire is written. Standard Filament components are the default; hand-rolled Livewire/Alpine is reserved exclusively for the AI Copilot surfaces where no first-party equivalent exists. This keeps the Phase 1 build cost aligned with the 100%-reuse rationale of [`ADR-001`](adr/ADR-001-stack-selection.md).

| UI Surface (`UI.md`) | Filament v4 Construct | Build Type |
| :--- | :--- | :--- |
| **Dashboard** | `Filament\Pages\Dashboard` + `StatsOverviewWidget`, `ChartWidget`, table widgets with polling | **Standard** |
| **Project Register (index)** | `ProjectRegisterResource` table: tabs, filters, grouping, badges, summarizers | **Standard** |
| **Project Register (detail)** | Resource `view` page infolist + **Relation Managers** (register entries, linked notes, action receipts) | **Standard** |
| **Personal Notes / Tasks** | User-scoped resources with policies, table tabs, and slide-over create/edit forms | **Standard** |
| **Activity Rollups** | Read-only `ActivityRollupResource` with infolist detail | **Standard** |
| **Executive Settings** | Custom Filament page with sovereign encrypted key storage & live probe testing | **Standard (light custom)** |
| **Global Search (`Cmd+K`)** | Filament global search: `getGloballySearchableAttributes()`, result details + result actions | **Standard** |
| **Notifications / Bell** | Filament **database notifications** with unread badge and polling | **Standard** |
| **Navigation & Badges** | Panel navigation groups + `getNavigationBadge()` counts (pending cards, unsynced items) | **Standard** |
| **AI Copilot Drawer / Chat** | Custom Livewire component injected via panel **render hook** (`panels::body.end`) | **Custom** |
| **Action Dossier Cards & `ask_user_question` modals** | Custom Livewire components composed from Filament Action/modal primitives | **Custom** |
| **Mobile bottom nav & bottom sheets** | Custom Blade/Alpine layer over the panel (mobile breakpoints only) | **Custom** |

**Layout rule**: the "3-column adaptive" desktop shell in [`UI-03`](UI.md) is realized as *Filament sidebar (column 1) + resource list page (column 2) + record detail / slide-over + docked Copilot drawer (column 3)* — **not** a bespoke replacement of the panel layout. A fully custom shell forfeits Filament's free functionality (search, notifications, responsive tables, accessibility) and is explicitly out of scope for Phase 1.

---

## [DESIGN-08] Runtime Integration Settings & Graceful Error Diagnostics (ADR-017)

Governed by [`ADR-017`](adr/ADR-017-runtime-integrations-and-graceful-error-fallback-guards.md) and [`docs/CONFIGURABLES.md`](CONFIGURABLES.md), all external integration credentials and runtime parameters are managed via the web UI with strict error guards:

1. **Sovereign User Encryption**:
   - `anthropic_api_key`, `gemini_api_key`, and `microsoft_client_secret` are encrypted at rest per-executive in the `users` table via AES-256 (`encrypted` Eloquent cast).
2. **Instant Reflection & Preflight Probes**:
   - Saving settings immediately evicts stale Spatie cache (`SettingsContainer::clearCache()`) and rehydrates authenticated user models.
   - Interactive **Test Connection** actions perform non-blocking 1-token / OAuth probes against Anthropic, Gemini, and Microsoft Graph.
3. **Provider-Direct Error Diagnostics & Remediation Cards**:
   - Upstream error payloads (e.g. `invalid_x_api_key`, `API_KEY_INVALID`, `AADSTS7000215: Invalid client secret`, `ErrorAccessDenied`) are intercepted, sanitized of sensitive tokens, and rendered alongside step-by-step fix guides.
4. **Graceful Degradation Boundary**:
   - Missing or misconfigured Microsoft Graph credentials trigger non-blocking degraded mode where all non-email features (FTS5 Project Register, Personal Notes, Tasks, AI reasoning) remain 100% operational.

---

## [DESIGN-09] OpenRouter Gateway & In-Chat 3-Favorites Runtime Swapper (ADR-018)

Governed by [`ADR-018`](adr/ADR-018-openrouter-multi-model-catalog-and-runtime-favorites-swapper.md), the system integrates the `ai-openrouter-gateway` pattern into `LlmGatewayService` and the Copilot Drawer:

1. **Unified Multi-Model Routing Engine**:
   - Supports `openrouter_api_key` alongside direct Anthropic/Gemini provider keys.
   - Normalizes requests to OpenRouter's OpenAI-compatible completions endpoint (`https://openrouter.ai/api/v1/chat/completions`) with custom site referer and title headers.
2. **Executive Top-3 Favorites Subsystem**:
   - Stores up to 3 preferred model tuples (`provider`, `model_id`, `display_label`) per executive in `ExecutiveSettings`.
   - Defaults to Slot 1 (`Claude 3.7 Sonnet`), with Slots 2 & 3 mapped to high-speed or specialized models (e.g., `DeepSeek R1`, `Gemini 2.5 Flash`).
3. **In-Chat Livewire Swapper Component**:
   - Copilot drawer header renders a compact 2-item badge (`[ Provider · Active Model ▾ ]`).
   - Clicking opens an ephemeral 3-option quick-switch popover; selecting a favorite instantly swaps the active model for subsequent turns in the current session without losing conversational history or active Action Cards.


