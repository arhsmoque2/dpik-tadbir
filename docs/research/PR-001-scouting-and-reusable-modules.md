# PR-001: Scouting Report — ARH-URUS, DPIK Tugas Laravel & DPI WorkOps
## Best Practices, Quality Gates, CI/CD & Low-Cost Reusable Modules for DPIK Tadbir

**Date**: 2026-08-29  
**Target Repository**: `dpik-tadbir`  
**Sources Audited**:
1. `ARH-URUS` (`D:\ARH-GITHUB\arhsmoque2\ARH-URUS`)
2. `DPIK Tugas Laravel` (`D:\ARH-GITHUB\arhsmoque2\dpik-tugas-laravel`)
3. `DPI WorkOps` (`D:\ARH-GITHUB\arhsmoque2\dpi-workops`)

---

## 1. Quality Gates & CI/CD Comparison Matrix

| Area | `ARH-URUS` | `DPIK Tugas Laravel` | `DPI WorkOps` | **Adoption Decision for DPIK Tadbir** |
| :--- | :--- | :--- | :--- | :--- |
| **Static Code Quality** | Laravel Pint + PHPStan / Larastan | Laravel Pint + Larastan (Level 8) | Oxlint + Ruff + Biome | **Pint + Larastan Level 8** (strictly typed PHP 8.4) |
| **Test Strategy** | Pest Feature Tests + Hallucination Guard | Pest Unit/Feature + Policy Tests | Pytest + Playwright E2E | **Pest PHP + SQLite Hermetic Sandbox + Diff-Cover (90%)** |
| **Security Gates** | Fail-Closed Token Limits + Module Scoping | Personal Task/Note Policy Isolation | Secret Scanner (`arh-doctor.mjs`) + Fail-Closed API | **Secret Preflight + Write Safety Approval Gate + Policy Scoping** |
| **GCP Cloud Auth** | Workload Identity Federation (WIF) | Workload Identity Federation (WIF) | Workload Identity Federation (WIF) | **WIF Keyless Authentication** (Zero static GCP JSON keys) |
| **Docker Build** | GHA Runner Buildx + Layer Caching (`type=gha`) | GHA Runner Buildx + Layer Caching (`type=gha`) | Multi-stage Docker + Cloudflare Pages | **Runner Buildx + GHA Layer Cache** (Fast, zero log-tail drops) |
| **Deploy Trigger** | `workflow_run` (gated strictly on CI pass) | `workflow_run` (gated strictly on CI pass) | Push / Workflow Dispatch | **`workflow_run` gated on CI success** |
| **Database Strategy** | Neon Serverless Postgres (Pooler + Direct DDL) | Neon Serverless Postgres | Dual Profile (SQLite / Neon SQL) | **Dual Profile (SQLite `:memory:` test / Neon Postgres prod)** |

---

## 2. CI/CD & Deployment Best Practices to Adopt

### A. Keyless GCP Workload Identity Federation (WIF)
* **Pattern**: Instead of saving permanent GCP service account JSON keys in GitHub Secrets (which can leak), authenticate via `google-github-actions/auth@v3` using GitHub's OIDC ID token.
* **Why it matters**: Zero credentials to rotate; access is strictly scoped to the repository identity (`arhsmoque2/dpik-tadbir`).

### B. Reliable Runner-Side Docker Buildx with GHA Caching
* **Pattern**: `docker/build-push-action@v6` with `cache-from: type=gha` and direct push to Google Artifact Registry.
* **Lesson from Tugas & URUS**: Avoid `gcloud builds submit` because synchronous CLI log-tail drops can falsely fail deployment jobs even if the container built successfully. Building on the GitHub runner with GHA layer caching is faster, robust, and free of log drops.

### C. `workflow_run` Deployment Gating
* **Pattern**: Deployments are never triggered directly by `on: push: branches: [main]`. They run via `on: workflow_run: workflows: [CI]: types: [completed]` and check `github.event.workflow_run.conclusion == 'success'`.
* **Why it matters**: Eliminates deployment races and prevents broken code from deploying if unit tests or static analysis fail.

### D. Separation of DDL Migrations & Serving
* **Pattern**: DDL migrations run using Neon's direct connection string (`.neon.tech`) before shifting traffic, while the running app uses the pooled connection string (`-pooler.neon.tech`).

---

## 3. High-Value, Low-Cost Reusable Modules for DPIK Tadbir

By extracting proven modules across these three repositories, we can implement 80% of `dpik-tadbir` capabilities immediately with near-zero R&D cost:

### Detailed Breakdown of Reusable Modules

#### 1. AI Core & Tool Bridge (from `ARH-URUS`)
* **`AgentService.php` + `ToolRegistry.php`**: Multi-turn conversational loop that executes tools derived from `Laravel\Mcp\Server\Tool`.
* **Hallucination Detection Guard**: Rejects responses where the LLM claims it updated a record or sent an email without actually invoking a tool.
* **`VaultEncryptionService.php`**: Encrypts sensitive personal data, SMTP credentials, and API tokens using AES-256-GCM.
* **`AiUsageService.php`**: Tracks input/output tokens per turn, logs costs, and protects against runaway agent loops.

#### 2. Staff & Project Management Domain (from `DPIK Tugas Laravel`)
* **Project & Workload Hierarchy**: `Project`, `Epic`, `Ticket`, `Department`, `Position`, `PositionAssignment`.
* **Filament Resource Blueprint**: Ready-to-use Filament schemas for Projects, Tickets, and Staff with search filters, status badges, and tab layouts.
* **Private Notes & Tasks**: `PersonalTask` and `ProjectNote` with built-in policies ensuring private records remain inaccessible to other users.

#### 3. Email Ingestion & Project Classification (from `DPI WorkOps`)
* **Headless Gmail Bridge (`ADR-0006`)**: Non-expiring Google App Password pattern (`imap.gmail.com:993`) for 24/7 background mailbox polling without OAuth refresh token failures.
* **`ProjectClassifier`**: Regex and metadata classification rule engine that matches incoming email subjects and sender domains to active project codes (e.g. `PC-2023-011`, `JPS Kelantan`).
* **Interactive Approval State Machine**: `ActionCard` pattern that transitions actions through `pending` $\rightarrow$ `approved` / `rejected` with explicit human receipts.

---

## 4. Summary Recommendation

By combining:
1. The **AI Tool & Agent engine** from `ARH-URUS`,
2. The **Staff, Ticket & Project models** from `DPIK Tugas Laravel`,
3. The **Headless Gmail Bridge & Project Classifier** from `DPI WorkOps`,
4. The **Outlook MCP** Graph API integration,

we achieve a feature-complete, secure, AI-assisted executive command center for the Managing Director with minimal code overhead and proven production patterns.
