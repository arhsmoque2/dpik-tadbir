# DPIK Tadbir: Implementation Brief — Bundles, AI-Optional Review & Adaptive Navigation

> **Audience**: a fresh session picking up where this one left off. Read this before writing code — it translates [`ADR-022`](adr/ADR-022-bundle-based-retrieval-ai-optional-review-and-adaptive-navigation.md), [`CAP-019`](CAPABILITIES.md#cap-019-bundle-based-retrieval-default-filter--auto-promotion-engine), [`CAP-020`](CAPABILITIES.md#cap-020-adaptive-mobile-navigation-with-fixed-copilot-anchor), and [`SCEN-08`](SCENARIOS.md)/[`SCEN-09`](SCENARIOS.md) into an actual build order against the real codebase, not the HTML previews.

## Status: this is 100% undesigned-into-code

Everything below is decided in docs and demonstrated in standalone HTML previews (sent directly to the operator, not committed to this repo). **None of it exists in the Laravel/Filament codebase yet.** Verified directly, not assumed: no `bundles` table, no `Bundle` model, no Bundle Filament resource, no nav-customization model, no filter/auto-promotion logic. `app/Models/ChatSession.php` and `ChatMessage.php` exist (the AI-session side); the Bundle side (the human-readable retrieval side) is net new. This doc is the build order for that net-new work — don't re-derive the design, it's already made; do verify it against the real codebase as you go, since the previews were built without one.

## Open decision to resolve before Phase 1 — do not silently pick a side

**`DESIGN-02` commits to zero raw email storage**: *"Raw Outlook Emails — Microsoft Exchange/Graph API — Zero raw storage (ephemeral context only) — discarded after turn."* This was written when Copilot chat was the only consumer of retrieved mail (fetch → summarize → discard). **A Bundle's whole purpose is to let a human come back and read retrieved emails later** — which needs *something* to persist beyond the single turn that fetched it.

Two ways to reconcile, not yet chosen:

1. **Metadata-only persistence**: a Bundle stores only `message_id`, `from`, `subject`, a short `snippet` (Graph API already returns a preview snippet in list/delta calls — no separate body fetch), and `received_at` per email. Opening a Bundle to read a *full* message still calls `OutlookReadMessageTool` live, on demand, per email — nothing beyond the index is ever written to disk. This is the smaller change to `DESIGN-02`'s spirit (it's an index, not a copy) and matches what `OutlookListInboxDeltaTool`/`OutlookSearchMailTool` already return in `concise=True` mode.
2. **Snapshot persistence**: a Bundle stores the snippet *and* is willing to re-fetch bodies lazily but cache them (e.g. Laravel cache with a TTL) rather than calling Graph on every read. Cheaper on API calls, slightly further from "zero raw storage."

**Recommendation, not a decision**: go with (1). It keeps `DESIGN-02`'s guarantee essentially intact (a persisted *pointer + preview*, not a persisted *copy*) and reuses tools that already exist. But this is a real amendment to an existing, load-bearing architecture principle — treat it like `ADR-022` did for `INTENT.md`: **write a short ADR amendment to `DESIGN-02`** stating which option was chosen and why, in the same PR as the `bundles` migration, rather than just shipping the migration and letting the doc go stale. Don't skip this step because the previews already "look approved" — the previews never had to answer this question; the schema does.

---

## Phase 1 — Bundle data model & retrieval

**Goal**: `outlook-mcp` retrieval produces a queryable `Bundle`, not just ephemeral chat context.

- Migration `bundles`: `id`, `user_id` (FK, scope everything to `auth()->id()` like `PersonalNote`/`PersonalTask`), `filter_label` (string, e.g. `"Today · Direct · New"`, `"PC-2023-011"`), `filter_criteria` (json — `sent_after`, `direct_only` bool, `delta_only` bool, `project_code` nullable), `retrieved_at`, `email_count`, `notes` (text, nullable — the human's own free-text field from `SCEN-08`).
- Migration `bundle_emails` (or a json column on `bundles` if the count stays small — decide based on `email_count` realistically expected per retrieval): `bundle_id`, `message_id`, `from_name`, `from_email`, `subject`, `snippet`, `received_at`. **No body, no attachments** — see the open decision above.
- `App\Models\Bundle` + `App\Policies\BundlePolicy` (copy `PersonalNotePolicy`'s shape exactly — `viewAny`/`view`/`update`/`delete` all scoped to `$user->id === $bundle->user_id`).
- New MCP tool or an extension of `OutlookListInboxDeltaTool`/`OutlookSearchMailTool`: after a Graph fetch, persist the result set as a new `Bundle` + its `bundle_emails` rows, returning the `Bundle` reference to the caller (UI button or Copilot). This is the retrieval-to-Bundle bridge — right now those tools return data to the LLM and nothing else.
- **Default filter** (`CAP-019`): when no explicit filter is chosen, retrieval means `sent_after: today`, `direct_only: true` (Graph query filters `to` recipients, excludes `cc`-only matches), `delta_only: true` (only messages newer than the user's last retrieval — track a `last_retrieved_at` per user, e.g. on `users` or a small settings row).

**Acceptance**: `SCEN-01`'s "manual" exit path is possible — a `Bundle` row exists with real `bundle_emails` after a retrieval, independent of whether Copilot was ever invoked.

## Phase 2 — Bundle UI (read + annotate, zero AI)

**Goal**: `SCEN-08` end to end, with no Copilot involvement.

- `App\Filament\Resources\BundleResource`: list page (title/filter_label, email_count, retrieved_at — mirrors `ProjectRegisterResource`'s shape), detail/view page listing `bundle_emails` (expandable rows — tapping one calls `OutlookReadMessageTool` live for the full body per the Phase 1 decision, not from a persisted column) plus a `notes` textarea bound to `Bundle::notes` with a save action.
- No Livewire needed beyond what Filament's own form/table components give you — this is squarely "Standard" per `DESIGN-07`'s build-vs-buy table, not a custom surface.

**Acceptance**: open a Bundle, expand an email, write a note, save, reload — the note persists, no AI call happened anywhere in this flow.

## Phase 3 — Auto-promotion

**Goal**: `SCEN-09`.

- A scheduled or on-retrieval check: `SELECT project_code, COUNT(*) FROM bundles WHERE user_id = ? AND retrieved_at > now() - 7 days GROUP BY project_code HAVING COUNT(*) > 3`. (Project code association on a Bundle likely comes from matching `filter_criteria->project_code` when set, or from cross-referencing `bundle_emails` against `project_registry_entries` if the project wasn't the explicit filter — the previews assumed the former; confirm which before building, since the two need different queries.)
- Surface the result as a quick-filter option in whatever retrieval-filter UI Phase 1's tool exposes — a small `is_auto_promoted` computed flag is enough; no separate table needed unless the promotion needs to persist across the threshold no longer being met (per `ADR-022`'s explicitly-deferred demotion question — don't build demotion logic; the ADR says this is intentionally open).

**Acceptance**: retrieve 4+ Bundles referencing the same `project_code` within 7 days; the filter picker surfaces that project without manual setup.

## Phase 4 — Copilot on a Bundle (optional layer)

**Goal**: the AI-assisted half of `SCEN-01`, and the "Ask Copilot about this Bundle" button from the previews.

- Add a nullable `bundle_id` FK to `chat_sessions` (or `chat_messages`, depending on whether "working on a Bundle" is session-scoped or can change mid-session — the previews modeled it as session-scoped via a bundle-picker chip row, so `chat_sessions.bundle_id` matches that).
- `AgentService`/`MemoryRetrievalService` context assembly: when a `ChatSession` has a `bundle_id`, inject that Bundle's `bundle_emails` (snippets, not bodies — same zero-raw-storage boundary) into the prompt context, same pattern as existing `DenseContextFormatter`.
- Preset prompts (already named in the previews, wire these as `ExecutivePreset` seeds or preset buttons): *"Summarize the latest retrieved bundle"*, *"Draft an email reply for me"*, *"Find emails about [topic]"*.
- The "Ask Copilot about this Bundle" button on `BundleResource`'s view page calls `Dashboard::startNewSession()`'s existing pattern, just with `bundle_id` set on the created `ChatSession`.

**Acceptance**: from a Bundle's detail page, tapping "Ask Copilot" opens a chat session pre-scoped to that Bundle; a summary request only references that Bundle's emails.

## Phase 5 — Adaptive mobile navigation

**Goal**: `CAP-020`.

- A small user-scoped preference model (`user_nav_preferences` or a json column on `users`/`user_personalization_profiles` — the latter already exists and already stores per-user UI-adjacent state, so prefer extending it over a new table) storing: ordered list of the 6 candidate destination ids, and which are in the visible set (cap 4).
- A Blade/Alpine mobile bottom-bar partial (per `DESIGN-07`, this is explicitly a **Custom** surface, not standard Filament — same bucket as the existing floating nav in `resources/views/filament/hooks/bottom-nav.blade.php`, which this likely replaces or extends rather than duplicates). Copilot's center slot is hardcoded in the template, never sourced from the preference model.
- A Customize Navigation screen (simple Filament custom page, form-bound to the preference model) — the previews' reorder-arrows + toggle-switches pattern.
- Home's expand-to-menu behavior and the Retrieve icon's chip-plus-picker behavior are both Alpine.js state on top of the existing bottom-nav partial, not new backend surfaces.

**Acceptance**: toggling a destination off in Customize Navigation removes it from the bottom bar (or moves it to the More drawer once 4 are already visible) without touching Copilot's fixed center slot.

## Phase 6 — Dashboard becomes the Bundle/Session list

**Goal**: `CAP-009`'s narrowed definition, replacing `Dashboard.php`'s current shape.

- `App\Filament\Pages\Dashboard` currently only exposes `startNewSession()`/`deleteSession()` against `ChatSession` with no listing UI shown in the class itself (the view file `filament.pages.dashboard` — read it before changing anything, it may already be closer to this than the class suggests). Rework the underlying Blade/table to list sessions with: title, model, provider, `retrieved_at`/timestamp, linked `Bundle` (if any), and a report-exists indicator (this last one needs a `has_report` concept — check whether `ChatSession` or a related model already tracks generated-report state before inventing a new column).
- No stat cards, no widgets, per `CAP-009`/`UI-11`. If a future feature genuinely needs one, that's a new `CAP` entry with its own justification, not a default.

**Acceptance**: `/admin` shows the session list matching the previews' shape, with zero stat-card widgets.

---

## Explicitly out of scope for this build pass

- **PDF export/download/share of a generated report** (`CAP-006` extension, raised in the same design review as everything above) — not designed yet. Needs its own flow-of-events pass before implementation, not folded into this brief.
- **`docs/ui-spec/mockup-preview.html`'s full rewrite** (797 lines, still shows the pre-Bundle 3-pane shell) — a documentation task, not a code-build phase; do it whenever, independent of the phases above.
- **Auto-promotion demotion** (a project falling back below the 7-day/3-session threshold) — deliberately left unspecified in `ADR-022`; don't invent a decay rule without checking with the operator first.

## Verification posture

Each phase above should land as its own PR against this repo's existing gates (`AGENTS.md`, `DEVTOOLS.md`) — Pint/PHPStan L8/FilaCheck, 90% incremental diff-cover, and (for anything touching `resources/views/filament/**` or a new Filament page) a real Gate 4 Playwright run, now that Gate 4 actually tests what it claims to. Don't treat the HTML previews as a substitute for that — they were a design aid, not a spec that skips testing.
