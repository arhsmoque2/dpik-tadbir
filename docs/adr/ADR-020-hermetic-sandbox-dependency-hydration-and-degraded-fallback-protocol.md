# ADR-020: Hermetic Sandbox Dependency Hydration & Degraded Fallback Protocol

- **Status**: Accepted
- **Date**: 2026-08-31
- **Context**: ARH Zero-Ask Cloud Sandbox Independence (Pillar 6) & DevTooling Resilience

## Context & Problem

DPIK Tadbir defines an authoritative 5-Phase Pre-Push Auto-Fix Cascade (`DEVTOOLS.md` §3) designed to cut CI round-trip cost by over 90% via local execution of `filacheck --fix`, `rector process`, `pint`, `phpstan`, and `pest --coverage-clover` + `diff-cover`.

However, when autonomous AI agents operate inside ephemeral cloud sandboxes (e.g. Claude Code web containers, GitHub Codespaces, isolated Docker runners), running `composer install` frequently encounters **GitHub API rate limits (HTTP 403 / "Could not authenticate against github.com")** or network proxy restrictions while fetching package zipballs across 80+ distinct repositories.

Without a functional `vendor/` directory:
1. All local static analysis tools (`pint`, `rector`, `filacheck`, `phpstan`) and test harnesses (`pest`) are dead-on-arrival.
2. Agents are forced into high-token "manual reasoning & remote CI round-trip" loops, reading raw GitHub Actions logs and guessing AST fixes.
3. The promise of the local auto-fix cascade is broken in ephemeral sandbox environments.

## Decision

We establish the **ARH Two-Tier Dependency Hydration & Degraded Fallback Architecture**:

### 1. Automated Release-Asset Tarball Hydration (Tier 1 — Recommended)
- A dedicated GitHub Actions workflow (`.github/workflows/sandbox-cache.yml`) automatically builds and archives pre-installed `vendor.tar.gz` and `node_modules.tar.gz` assets upon any push to `main` modifying `composer.lock` or `pnpm-lock.yaml`.
- The artifacts are published to a permanent, rolling GitHub Release tag (`sandbox-vendor-latest`).
- In sandbox environments, `scripts/setup-sandbox.sh` fetches and unpacks `vendor.tar.gz` in a single streaming command (`curl -sL ... | tar -xz`), completing dependency hydration in <3 seconds without making 80+ separate GitHub API zipball requests.

### 2. Authenticated Composer Token Injection (Tier 2 — Secondary)
- If the pre-built tarball is unavailable or dependencies have drifted on a feature branch, `scripts/setup-sandbox.sh` validates available authentication tokens (`GITHUB_TOKEN`, `GH_TOKEN`, or `gh auth token`) against strict format heuristics (rejecting tool proxies like `proxy-injected` and dummy placeholders) before injection into Composer's global configuration (`composer config -g github-oauth.github.com <TOKEN>`).
- If token injection is invalid or unauthenticated install fails, the script continues cleanly into independent Node/Playwright/SQLite setup rather than cascading into a fatal termination.

### 3. Degraded CI-Feedback Protocol (Tier 3 — Honesty Doctrine)
- When running in an air-gapped sandbox where network egress is entirely blocked:
  - The agent explicitly operates in **Degraded CI-Feedback Mode**.
  - The agent leverages machine-parseable CI error logs from remote GitHub Actions runs.
  - The agent is prohibited from attempting blind, repetitive local install loops.

## Consequences

- **Positive**: Cloud sandbox agents can achieve instant (<3s), rate-limit-immune local environment hydration via pre-compiled release bundles.
- **Positive**: The full `DEVTOOLS.md` 5-Phase Auto-Fix Cascade becomes active and reliable in cloud sandbox containers.
- **Positive**: Strict adherence to the Honesty Doctrine: agents know exactly when to rely on local tools vs. falling back to structured CI feedback without token waste.
