# Session Handoff & Resumption State

**Written**: 2026-08-31T01:58:41Z (UTC) — this supersedes any earlier content in this file. If you're reading this and it's more than a few days old relative to your session start, treat the "Known current state" section as a starting hypothesis to re-verify, not gospel — everything else (file paths, architecture) is stable.

**Repository**: `arhsmoque2/dpik-tadbir`
**Branch this was written from**: `claude/dpik-tadbir-quality-audit-qt5vcp` (HEAD `3fd5b3f`, in sync with `main`)
**Nature of this session**: read-only quality/UI/AI audit — nothing in dpik-tadbir has been changed yet. This file exists so the next session doesn't have to re-derive the findings below.

---

## 0. Required session scope for the next task

The next task (Section 5) needs **`arhsmoque2/ARH-URUS`** attached in addition to `arhsmoque2/dpik-tadbir`. The account-level GitHub grant already covers it (confirmed via `list_repos`), but a session only gets what it's scoped with at creation — there was no in-session way to widen scope when this was written. **Start the next session with both repos in scope**, or attach `ARH-URUS` immediately if the environment supports it.

---

## 1. The task

Port DPIK Tadbir's AI chat module over to reuse what already works in **ARH-URUS**: the AI chat orchestration, the MCP tool schema/registration pattern, and the system prompt(s) — repurposed for DPIK Tadbir's domain (executive command center for the Managing Director, not ARH-URUS's original domain) — **plus** wire in Outlook MCP (dpik-tadbir already has a partial Outlook MCP tool set — see Section 3 — check whether ARH-URUS's pattern subsumes or should be merged with it).

Do not rediscover dpik-tadbir's current AI implementation or its defects — it's fully diagnosed below. Do start by reading ARH-URUS's own docs (its AGENTS.md/CLAUDE.md/README, if any) to find where its chat module, tool schemas, and system prompt actually live before assuming a layout.

---

## 2. Known current state of dpik-tadbir (audited this session — don't re-discover)

Evidence basis: source reading plus the **live production deploy log** for the current `main` HEAD (GitHub Actions run #23, job "Gate 5: Build, Migrate & Deploy to Cloud Run", job id `99300407422`) — not just docs/tests, which is why some of this contradicts `CURRENT_STATE.md`'s "100% green" framing.

1. **Auth is bypassed in production by default.** `config/auth.php:11` → `env('AUTH_ENABLED', false)`; `.github/workflows/deploy.yml:131` → `AUTH_ENABLED=${{ vars.AUTH_ENABLED || 'false' }}`. Confirmed live in the deploy log: `AUTH_ENABLED=false`, `APP_DEBUG=true`, service deployed `--allow-unauthenticated`. `app/Http/Middleware/AutoLoginBypassMiddleware.php` auto-logs any visitor in as `super_admin` when disabled. **The user is deciding how/when to handle this separately — do not touch auth config or the bypass middleware without an explicit go-ahead in that session.**

2. **The AI Copilot never calls a real Anthropic or Gemini API — only OpenRouter is real.** `app/Services/Ai/LlmGatewayService.php:144-186` (`invokeProvider`): only the `openrouter` branch makes a live HTTP call; `anthropic` (the configured `default_provider`) and `gemini` (the `fallback_provider`) fall straight through to `mockCompletion()` (line 355), a hardcoded keyword-matcher, in every environment. There's no `invokeAnthropic`/`invokeGemini` method in the file. **This is the core defect the ARH-URUS port needs to fix** — the ported module needs to actually reach a model by default, not just when a user has personally configured OpenRouter.

3. **Executive Presets are invisible to the actual logged-in user.** `database/seeders/DatabaseSeeder.php` scopes the seeded "Tender Review Brief" preset to `admin@dpik.com.my`'s `user_id`, but `AutoLoginBypassMiddleware` logs in as the first `super_admin` by seed order (`rahman@dpik.com.my`). Both `app/Livewire/AiCopilotDrawer.php:434-446` (`presets()`) and `app/Filament/Resources/ExecutivePresetResource.php:38-44` (`getEloquentQuery()`) scope to `where('user_id', auth()->id())->orWhereNull('user_id')`, so the mismatch means an empty ribbon and an empty resource table for the real user. Confirmed visually (screenshots) on both surfaces. A Playwright assertion that caught this (`expect(presetBtn.first()).toBeVisible()`) was weakened in PR #17 to just check the "Presets:" label renders, rather than the bug being fixed.

4. **Production never builds the app's own CSS.** No `vite.config.*`, no `resources/css/*.css`, no `@vite(...)` call anywhere, and the `Dockerfile` has no Node/npm stage at all (PHP/Composer-only, multi-stage). Filament's own pre-bundled CSS ships fine (chrome, nav, Filament-native resource tables/widgets all render correctly — confirmed by screenshot). But hand-rolled Blade pages using raw Tailwind utility classes — `resources/views/filament/pages/executive-assistant.blade.php` and `resources/views/filament/pages/executive-settings.blade.php` — have zero matching CSS in production and render unstyled (confirmed by screenshot: giant unstyled SVG icons, no cards/spacing/color). **User confirmed this is scoped to just Settings + Copilot pages, not the whole app.**

5. **The AI Copilot drawer's open button is unreliable — double-toggle race.** Both the hero-page button (`executive-assistant.blade.php:18`) and the topbar button (`resources/views/filament/hooks/copilot-topbar-button.blade.php:4`) do `$dispatch('toggle-copilot-drawer')`, a raw browser `window` event. It's caught by **two independent listeners** that both flip the same `@entangle`'d `isOpen` property: Alpine client-side instantly (`ai-copilot-drawer.blade.php:13`, `@toggle-copilot-drawer.window="isOpen = !isOpen"`) and Livewire server-side via its automatic `#[On('toggle-copilot-drawer')]` listener (`AiCopilotDrawer.php:129`, `toggleDrawer()`). One click fires both, and depending on race order the drawer opens then immediately snaps shut, or never visibly opens. **User confirmed: "can't open the ai chat."** `⌘J` works because it only calls `$wire.toggleDrawer()` directly (single source of truth, `ai-copilot-drawer.blade.php:8`) — no raw dispatch involved. Fix: pick one mechanism (likely: drop the PHP `#[On('toggle-copilot-drawer')]` listener and its `mount()` re-init guard, let Alpine own the toggle, keep `open-copilot-drawer`/`ask-copilot-about` as the separate server-authoritative listeners they already are for flows that seed a prompt).

6. **Microsoft 365 / Outlook has zero live credentials in production.** Deploy log confirms `MICROSOFT_CLIENT_ID=`, `MICROSOFT_CLIENT_SECRET=`, `MICROSOFT_TENANT_ID=` all blank. Executive Settings page shows "Not Configured" with placeholder zeros — this is accurate, not a bug, but means the Outlook-side AI capabilities have nothing live to talk to right now even where the code path is real.

7. **Minor doc drift**: `AGENTS.md` states tools register in `App\Services\Agent\ToolRegistry` — actual location is `app/Mcp/ToolRegistry.php` (see Section 3). Worth a one-line AGENTS.md fix whenever convenient, not urgent.

None of the above (except #6, which is an unconfigured-integration state, not a bug) is caught by the 4-gate CI pipeline (Pint, PHPStan L8, FilaCheck, 83 Pest tests, Playwright+axe, visual regression) — the tests either assert the broken behavior as intended (auth bypass), never exercise the real integration (AI providers), or had their assertion weakened the moment they caught something real (presets ribbon).

---

## 3. Relevant dpik-tadbir file map (for the port)

- `app/Services/Ai/LlmGatewayService.php` — provider gateway; only the `openrouter` branch is real (see #2 above)
- `app/Services/Ai/AgentService.php` — turn handling, wires `LlmGatewayService` + `ToolRegistry`, persists `AiRun` telemetry
- `app/Services/Ai/CostCalculator.php` — per-token pricing table
- `app/Mcp/ToolRegistry.php` — **actual** MCP tool registration point (not `App\Services\Agent\ToolRegistry` as AGENTS.md currently claims). Already registers 12 tools: `AskUserQuestionTool`, `ProposeActionCardTool`, 6 Outlook tools (`OutlookCreateDraftTool`, `OutlookReplyTool`, `OutlookForwardTool`, `OutlookSearchMailTool`, `OutlookListInboxDeltaTool`, `OutlookReadMessageTool`), `QueryProjectRegisterTool`, `CommitProjectRegisterTool`, `CreatePersonalNoteTool`, `CreatePersonalTaskTool`. All under `app/Mcp/Tools/**`, extending some `BaseTool`.
- `app/Services/Mcp/OutlookMcpBridge.php` — existing Outlook MCP bridge (`forUser()` scoping, executes tools against an Outlook MCP server) — likely the integration point to reconcile with whatever ARH-URUS does for Outlook, rather than building a second one.
- `app/Livewire/AiCopilotDrawer.php` + `resources/views/livewire/ai-copilot-drawer.blade.php` — the chat UI itself (has the double-toggle bug, #5 above)
- `config/services.php` — `services.ai.*` config (`default_provider`, `default_model`, `fallback_provider`, `fallback_model`, per-provider keys)
- Governing ADRs: `docs/adr/ADR-002-ai-model-and-provider-governance.md`, `ADR-007-write-safety-human-in-the-loop-approval-gates.md`, `ADR-011-interactive-ui-modals-and-human-in-the-loop-tools.md`, `ADR-017-runtime-integrations-and-graceful-error-fallback-guards.md`

---

## 4. Explicitly deferred / not yet touched

- Nothing in dpik-tadbir has been fixed yet — this was audit-only.
- Auth bypass (#2 in the numbered list above is AI; the auth item is #1) is being decided separately by the user — do not change `AUTH_ENABLED` defaults, `AutoLoginBypassMiddleware`, or `DatabaseSeeder`'s production guard without a fresh explicit instruction.

## 5. Immediate next steps for the new session

1. Confirm `arhsmoque2/ARH-URUS` is attached (Section 0).
2. Read ARH-URUS's own docs first to locate its chat module, MCP tool schema, and system prompt rather than guessing a layout.
3. Compare its tool-registration pattern against dpik-tadbir's existing `App\Mcp\ToolRegistry` (Section 3) — reuse dpik-tadbir's pattern where they conflict, since it's already wired into Filament/Livewire here; port ARH-URUS's actual tool *implementations*, schemas, and system prompt content into it.
4. Reconcile Outlook: dpik-tadbir already has 6 Outlook MCP tools and a bridge (`OutlookMcpBridge`) — check whether ARH-URUS's Outlook approach is meant to replace, extend, or just validate this one.
5. Fix the mock-completion defect (#2 in Section 2) as part of this port — the ported module must actually call a real model by default, not fall back to canned text.
6. Rehearse for real: an actual chat round-trip against a live model, not just green tests (see `.claude/skills/flow-of-events-first/SKILL.md` if present, or general observable-rehearsal discipline) — this repo's CI has repeatedly gone green on mocked/bypassed behavior, so don't trust gate-passing as proof here.
