# PR-010: Relaticle Chat Package — Verified Source Audit

- **Document ID**: `PR-010-RELATICLE-CHAT-VERIFIED-AUDIT`
- **Date**: 2026-09-02
- **Target Repository**: `dpik-tadbir`
- **Source Repository Audited**: [`relaticle/relaticle`](https://github.com/relaticle/relaticle), commit `b09a394a9917f8c4b68f427fc3780511e48e3eb2` (2026-09-02 01:16 +04:00), shallow-cloned and read directly — every claim below traces to a file path and line count from that checkout, not to Relaticle's README or marketing copy.
- **Supersedes / corrects**: [`PR-003: Relaticle Architecture Patterns & Calm Enterprise UI/UX Paradigm`](pattern-research.md) and the draft `ADR-032` opened in PR #37, both of which described Relaticle's patterns secondhand (from a technical-review pass, not the source) and, per §4 below, got two load-bearing details wrong. This document replaces them as the audit trail for Relaticle adoption; `pattern-research.md`'s findings on quality-gate/observability patterns (its actual subject) are unaffected and still stand.

---

## 1. Executive Summary & Audit Context

The operator asked to adopt "all goods that would enhance the AI chat features" and the ChatGPT/Claude/Gemini-familiar layout from Relaticle, plus "the underneath engine that makes it behave near one" — by copying and importing the real patterns, not re-describing them. PR #37 attempted this from a research pass but never actually read the source repository; this audit does.

Finding, in one sentence: **`packages/Chat` is a real, mature, ~100-file first-party package** — the sidebar history, quick-switcher, composer, and multi-turn write-approval engine PR #37's ADR described do genuinely exist in Relaticle — but PR #37's own implementation of the one piece it tried to port (write-discipline Action classes) was a rough, disconnected, and bug-laden approximation, and its description of the "engine" piece (`$ref:` plan chaining) does not match what Relaticle actually built.

## 2. Audit Method

`GIT_LFS_SKIP_SMUDGE=1 git clone --depth 1 https://github.com/relaticle/relaticle` at the SHA above, then read the package source directly (`find`, `wc -l`, full-file reads of the pieces load-bearing enough to justify porting). No claim in this document is drawn from Relaticle's own documentation — only from code actually opened and confirmed present.

## 3. Verified Findings

### 3.1 Stack version delta

| | Relaticle | dpik-tadbir |
|---|---|---|
| Laravel | `^13.0` | 12 |
| Filament | `^5.0` | v4 |
| Livewire | `^4.0` | 3 |

Confirmed via `composer.json`. This is a full major-version gap on all three, not a patch difference — literal file copy will not compile. One piece of good news, checked directly: `grep -rl "#\[Island\]\|wire:island\|Livewire\\Attributes\\Island" packages/Chat/src packages/Chat/resources` returns nothing — the Chat package itself does not depend on Livewire 4's Islands feature, so its component logic is portable to Livewire 3 in principle. Filament 5's panel/hook/action APIs do have real breaking changes from v4 (confirmed by this repo's own `ADR-033`, which already had to work around Filament v4 render-hook specifics); every Blade view under `packages/Chat/resources/views/filament/` needs a v4-API adaptation pass, not a copy.

### 3.2 The write-discipline "Action" pattern — real, and correctly identified by PR #37

Confirmed pervasive: e.g. `packages/Chat/src/Actions/DeleteConversation.php`, `ListConversations.php`, `RenameConversation.php`, `FindConversation.php`, `SearchConversations.php`, `ListConversationMessages.php` — single-responsibility classes invoked as `(new DeleteConversation)->execute($user, $conversationId)`. PR #37's Pillar 1 (Action classes + a PHPStan AST rule forbidding direct Eloquent writes outside `app/Actions/`) is a faithful extraction of a real Relaticle convention. The implementation problems found in PR #37 (namespace case mismatch breaking autoload, a `RefreshDatabase`/`LazilyRefreshDatabase` trait collision fataling its own test, the AST rule breaking `GoogleController` because `App\Http\Controllers` was guarded but never grandfathered, `tests/Arch/ArchTest.php` never registered in `phpunit.xml` so it silently never runs under CI, and zero real call sites for any of the three new Action classes it added) are execution defects in a sound pattern, not a reason to reject the pattern itself.

### 3.3 The "engine" — PR #37's `$ref:` chaining is not what Relaticle built

PR #37 described a transient, same-request `$ref:<pending_action_id>` string substitution (`app/Services/AI/PlanReference.php`, resolved in-memory by `PlanReferenceResolver` inside one `DB::transaction()` call). What Relaticle actually built is materially different and more robust:

**`packages/Chat/src/Models/PendingAction.php`** (113 lines) — a **persisted** Eloquent model. Every AI-proposed write becomes a database row:

```php
#[Fillable([
    'team_id', 'user_id', 'conversation_id', 'turn_id', 'message_id',
    'action_class', 'operation', 'entity_type',
    'action_data', 'display_data',
    'status', 'expires_at', 'resolved_at', 'result_data',
])]
final class PendingAction extends Model
{
    use HasFactory, HasTeam, HasUlids;

    protected function casts(): array { return [
        'operation' => PendingActionOperation::class,
        'status' => PendingActionStatus::class,
        'action_data' => 'array', 'display_data' => 'array',
        'expires_at' => 'datetime', 'resolved_at' => 'datetime',
        'result_data' => 'array',
    ]; }

    #[Scope] protected function pending(Builder $query): Builder { ... }
    #[Scope] protected function expired(Builder $query): Builder { ... }
}
```

Resolved by **`packages/Chat/src/Services/PendingActionService.php`** (1,102 lines — this is the real orchestration engine, not the 67-line stand-in PR #37 wrote). Because each proposal is a row, not a request-scoped variable, it survives page reloads, supports a real status machine (pending / resolved / expired / superseded — `PendingActionStatus`), carries its own `expires_at` TTL, and is addressable by `conversation_id`/`turn_id`/`message_id` for "the user asked something new, supersede the stale proposal" handling (`PendingActionsSuperseded` event). None of that is possible with a transient string that only exists inside one PHP request.

**Correction to PR #37 / draft ADR-032**: the ADR's Pillar 2 ("Immutable Tool History / Prompt Cache Preservation... `PlanReferenceResolver` Engine") describes the transient mechanism as if it were the Relaticle pattern. It is PR #37's own invention. The real pattern to port is the persisted `PendingAction` model + `PendingActionService`, described in §5 below adapted to this repo's existing analog rather than copied wholesale.

### 3.4 UI package inventory — confirmed present, this is the "familiar layout" ask

From `packages/Chat` (full file list captured during this audit):

- **History & navigation**: `Livewire/App/Chat/ChatSidebarNav.php` (66 lines) — nested in Filament's sidebar, lists recent conversations with inline rename/delete. `ChatAllChatsPanel.php` (79 lines) — the `Cmd+O`-style full history/quick-switcher panel. `ChatSidePanel.php`.
- **Conversation core**: `Livewire/Chat/ChatInterface.php` (419 lines) — the main chat surface; `ProposalCard.php` — the write-approval UI component.
- **Composer & transcript**: `resources/views/livewire/chat/partials/_composer.blade.php`, `_composer-bar.blade.php`, `_transcript.blade.php`, `_model-picker.blade.php`, `_switcher.blade.php`, `_message-search.blade.php`, plus proposal-specific partials (`_proposal-card.blade.php`, `_proposal-plan-card.blade.php`, `_proposal-item-chips.blade.php`, `_proposal-field.blade.php`, `_block-record-card.blade.php`, `_block-records-table.blade.php`).
- **Client-side**: `resources/js/chat.js` plus `chat/{blocks,copy,mention-chip,model-picker,send,stream,transcript,voice}.js` — streaming, `@`-mention chips, voice input, copy-to-clipboard, are each their own small module, not one monolith.
- **Supporting engine**: `Actions/*` (conversation CRUD, listed in §3.2), `Agents/{ConversationTitler,CrmAssistant,ModelProbeAgent,NextStepSuggester}.php`, `Jobs/{GenerateConversationTitle,ProcessChatMessage,SuggestNextSteps}.php`, `Http/Controllers/{ChatController,MessageFeedbackController,RecordRedirectController,TranscribeController}.php`.

A genuinely well-documented gotcha worth preserving verbatim when this is ported — from `ChatSidebarNav.php`'s own docblock:

> This component is nested inside Filament's sidebar, and a nested component's own re-render never reaches the DOM here: the server renders it and Livewire discards the html, so the list keeps whatever it showed at page load. ... Everything that changes this list therefore dispatches `refresh-sidebar` and nothing else.

That is a real, hard-won Filament-nesting quirk, not a hypothetical — it will bite the same way in Filament v4 if a ported sidebar nav tries to `$this->dispatch()` a self-refresh instead of the parent-level event.

### 3.5 Domain-model mismatch — the reason this cannot be a mechanical port

`PendingAction` and `AgentConversation` are `HasTeam`-scoped — Relaticle is a multi-tenant CRM (contacts, deals, companies). DPIK Tadbir's own signup-approval design is deliberately **single-family, not multi-tenant** (every approved signup joins `Family::first()`, always). Every team-scoped piece needs re-keying to this repo's existing `user_id`-sovereignty model (the same pattern PR #37's `TenantFkValidator` was reaching for, just for the wrong reason — cross-tenant leakage isn't the risk here, cross-*executive* leakage on a shared single-org install is).

## 4. Corrections to prior research (itemized, each with evidence)

1. **PR-003 / draft ADR-032 Pillar 2 mischaracterizes the plan-chaining mechanism.** Claimed: transient `$ref:` string resolved in one request. Actual (verified, §3.3): a persisted `PendingAction` Eloquent model with TTL, status machine, and a 1,102-line service — an entire subsystem, not a 67-line helper.
2. **PR-003 undercounts the UI surface.** Draft ADR-032's Pillar 3 lists UI aspirations (sidebar, `Cmd+O`, sticky date pill, composer) as a roadmap phase with no confirmation they exist in Relaticle as described. They do (§3.4), in more granular form than described (e.g. voice input and `@`-mentions are each standalone JS modules, not bullet points).
3. **PR #37 never actually wired any of what it ported.** Verified by grep: zero call sites for its three new Action classes or `ProposalPlanService` outside their own files and tests (session's PR #37 review, reproduced here as context for whoever reads this ADR next).

## 5. Mapping table — port target, not copy target

| Relaticle piece | dpik-tadbir's existing analog (confirmed present) | Adaptation needed |
|---|---|---|
| `AgentConversation` / `AgentConversationMessage` | `App\Models\ChatSession` (`user_id`, `title`, `context_mode`, `bundle_id`) / `App\Models\ChatMessage` | Re-key any ported UI to these existing models — do not introduce parallel conversation models. |
| `PendingAction` (persisted proposal + TTL + status machine) | `App\Models\AiActionReceipt` (`user_id`, `action_type`, `description`, `payload`, `status`, `approval_token`, `executed_at`) — already shaped like a lightweight `PendingAction` | Closest existing analog by far. Needs `expires_at` + a `superseded` status + `conversation_id`/`turn_id` linkage added, not a second table. |
| `ChatInterface` Livewire component's suspended-turn handling | `App\Livewire\AiCopilotDrawer` (623 lines) — `$suspendedToolCall` (single, in-memory, lost on reload), `approveActionCard()`, `discardActionCard()`, `submitChoice()`, `skipChoice()` | This is the real gap Relaticle's persisted model solves: today exactly one suspended tool call exists, held in a Livewire property, not a row — no TTL, no supersede, does not survive a page reload or session switch. |
| `ChatSidebarNav` / `ChatAllChatsPanel` | Nothing yet — `AiCopilotDrawer::newSession()`/`switchSession()` exist but no dedicated history/switcher UI | Greenfield for this repo; adapt Relaticle's component structure and its documented Filament-sidebar-refresh gotcha (§3.4) to Filament v4's render-hook mechanism (`app/Filament/Hooks/*`, per `ADR-033`). |
| `HasTeam` scoping throughout | `App\Support\TenantFkValidator` (from PR #37, itself unwired) + this repo's `user_id` sovereignty | Every ported query needs `HasTeam` stripped and replaced with the existing sovereignty pattern — not optional, this is the single biggest source of risk in a naive port. |

## 6. Traceability

- Source audited: `relaticle/relaticle@b09a394a9917f8c4b68f427fc3780511e48e3eb2`, read via a local shallow clone during this session — not secondhand.
- Governing ADR: [`ADR-034`](../adr/ADR-034-relaticle-chat-adoption-verified-and-phased.md).
- Superseded research: [`pattern-research.md`](pattern-research.md) (§ Relaticle-specific claims only — its quality-gate/observability findings are a separate subject and remain valid).
- Related, unmerged: PR #37 (`feat/relaticle-patterns-calm-ui-adr-032`) — its implementation defects are documented in this session's PR review; its underlying pattern selections are validated by §3.2 of this document and should inform, not be discarded by, the phased plan in `ADR-034`.
