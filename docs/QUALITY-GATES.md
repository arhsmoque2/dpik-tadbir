# DPIK Tadbir: Quality Gates & Pre-Merge Gate Suite

Modeled directly on the proven multi-tier quality gates from **`ARH-FNB-Beelal-Coffee`** and **`ARH-URUS`**, every pull request must pass 5 automated verification gates before merging.

---

## Gate 1: Static Analysis, Typing & Documentation Hygiene
- **Code Style (Laravel Pint)**: Zero formatting errors (`./vendor/bin/pint --test`).
- **Static Typing (Larastan / PHPStan)**: Level 8 strictness (`./vendor/bin/phpstan analyse --level=8`).
- **Documentation Linting (`markdownlint-cli2`)**: Zero markdown syntax errors or broken heading IDs across `docs/` and `adr/`.
- **Spelling & Lexicon (`cspell`)**: Zero spelling errors or typos across specs, comments, and schemas (`npx cspell --no-progress "**/*"`).
- **Dead Code Audit (`composer-unused`)**: Zero unused composer dependencies or orphaned service bindings.

---

## Gate 2: Security, Privacy & Write-Safety Invariants
- **Secret Preflight Scanner**: Zero API keys, Graph tokens, or SSH credentials in committed code or sample fixtures (`arh-doctor.mjs` / `gitleaks`).
- **Personal Privacy Policy Isolation**: 100% test verification that `PersonalNote` and `PersonalTask` models strictly enforce `auth()->id()` tenancy policies with zero admin leakage.
- **Fail-Closed Write Confirmation**: Verification that all outbound email dispatching tools (`outlook_send_message`, `outlook_reply`, `outlook_forward`) reject execution if no valid human confirmation token is present.

---

## Gate 3: Hermetic Test Suite & Incremental Diff Coverage (90%)
- **Hermetic SQLite Sandbox**: 100% passing tests under in-memory SQLite (`php artisan test --parallel`).
- **Incremental Diff-Cover Gate**: Minimum **90% branch coverage** on modified lines in all PRs (`diff-cover coverage.xml --fail-under=90`).
- **Tier 1 Critical Modules**: 95–100% coverage on `AgentService`, `MemoryRetrievalService`, and `OutlookMcpBridge`.

---

## Gate 4: Headless Playwright UI & Layout Audit (Beelal Coffee Pattern)
- **Local PR Checkout Audit**: A headless Playwright Chromium runner spins up the local app server and asserts:
  - Zero DOM overflow or broken tables across desktop (1280px) and mobile (375px) viewports.
  - Presence of all 8 core runtime states (`idle`, `loading`, `ready`, `active`, `success`, `empty`, `error`, `unavailable`) across Filament pages.
  - Interactive Action Card modal renders and handles escape hatches (`[Skip]`, `[Cancel]`).

---

## Gate 5: Safe Deployment Gating (`workflow_run`)
- **Zero Race Conditions**: Production deployment is **never** triggered directly by raw branch push.
- **Workflow Run Trigger**: Deploys execute strictly on `on: workflow_run: workflows: [CI]: types: [completed]` and assert `github.event.workflow_run.conclusion == 'success'`.

