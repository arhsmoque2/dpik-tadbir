# DPIK Tadbir: Quality Gates & Pre-Merge Gate Suite

Modeled directly on the proven multi-tier quality gates from **`ARH-FNB-Beelal-Coffee`** and **`ARH-URUS`**, every pull request must pass 5 automated verification gates before merging.

---

## Gate 1: Static Analysis, Typing & Documentation Hygiene
- **Code Style (Laravel Pint)**: Zero formatting errors (`./vendor/bin/pint --test`).
- **Static Typing (Larastan / PHPStan)**: Level 8 strictness (`./vendor/bin/phpstan analyse --level=8 --memory-limit=1G`).
- **Documentation Linting (`markdownlint-cli2`)**: Zero markdown syntax errors or broken heading IDs across `docs/` and `adr/` (`npx --yes markdownlint-cli2 "**/*.md" "#node_modules"`).
- **Spelling & Lexicon (`cspell`)**: Zero spelling errors or typos across specs, comments, and schemas (`npx --yes cspell --no-progress "**/*.md" "docs/**/*.json"` governed by `.cspell.json`).
- **Dead Code Audit (`composer-unused`)**: Zero unused composer dependencies or orphaned service bindings (`./vendor/bin/composer-unused`).

---

## Gate 2: Security, Privacy & Write-Safety Invariants
- **Secret Preflight Scanner (`gitleaks`)**: Automated preflight scan ensuring zero API keys, Graph tokens, or SSH credentials exist in committed code, fixtures, or environment examples (`gitleaks/gitleaks-action@v2`).
- **Personal Privacy Policy Isolation (`PersonalNotePolicy`, `PersonalTaskPolicy`)**: 100% test verification in `tests/Feature/Notes/PersonalNotePolicyTest.php` and `tests/Feature/Tasks/PersonalTaskPolicyTest.php` ensuring records strictly enforce `auth()->id()` user scoping.
- **Fail-Closed Write Confirmation (`WriteSafetyProposalTest`)**: Verification in `tests/Feature/Mcp/WriteSafetyProposalTest.php` asserting that all write-mutation tools (`OutlookCreateDraftTool`, `OutlookReplyTool`, `OutlookForwardTool`) reject dispatch if an approval token is missing, forged, or expired. *(Note: Ticket reassignment gate is deferred per [`ADR-012`](file:///D:/ARH-GITHUB/arhsmoque2/dpik-tadbir/docs/adr/ADR-012-scope-reduction-defer-project-staff-oversight.md))*

---

## Gate 3: Hermetic Test Suite & Incremental Diff Coverage (90%)
- **Hermetic SQLite Sandbox**: 100% passing tests under in-memory SQLite (`./vendor/bin/pest --coverage-clover coverage.xml --parallel`).
- **Incremental Diff-Cover Gate**: Minimum **90% branch coverage** on modified lines in all PRs (`uvx diff-cover coverage.xml --compare-branch origin/main --fail-under=90`).
- **Tier 1 Mission-Critical Modules (95–100% target)**:
  - `App\Services\Ai\AgentService`
  - `App\Services\Ai\AntiHallucinationGuard`
  - `App\Services\Ai\LlmGatewayService`
  - `App\Services\Mcp\OutlookMcpBridge`
  - `App\Services\Memory\MemoryRetrievalService`
  - `App\Services\Audit\ActionMemoryService`
  - `App\Mcp\ToolRegistry`
  - `App\Mcp\Tools\Outlook\OutlookCreateDraftTool`
  - `App\Mcp\Tools\Outlook\OutlookReplyTool`
  - `App\Mcp\Tools\Outlook\OutlookForwardTool`
  - `App\Policies\PersonalNotePolicy`
  - `App\Policies\PersonalTaskPolicy`
  - `App\Policies\ExecutivePresetPolicy`

---

## Gate 4: Headless Playwright UI & Layout Audit (Beelal Coffee Pattern)
- **Automated Layout & State Verification**: A headless Playwright Chromium runner spins up the local application server and asserts:
  - Zero DOM overflow or broken tables across desktop (1280px) and mobile (375px) viewports.
  - Presence of all 8 core runtime states (`idle`, `loading`, `ready`, `active`, `success`, `empty`, `error`, `unavailable`) across Filament pages.
  - Interactive Action Card modal renders properly and handles escape hatches (`[Skip]`, `[Cancel]`).

---

## Gate 5: Safe Deployment Gating (`workflow_run`)
- **Zero Race Conditions**: Production deployment is **never** triggered directly by raw branch push.
- **Workflow Run Trigger**: Deploys execute strictly via `.github/workflows/deploy.yml` on `on: workflow_run: workflows: ["Quality Gate CI"]: types: [completed]` and assert `github.event.workflow_run.conclusion == 'success'` on the `main` branch.
