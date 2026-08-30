# ADR-019: Proportionate CI Gating and Zero-Drift Documentation Verification

- **Status**: Accepted
- **Date**: 2026-08-30
- **Context**: ARH DevSuite CI Gating Standard and Multi-Tier Quality Hierarchy

## Context & Problem

DPIK Tadbir enforces a comprehensive 4-tier verification suite including Gate 1 (markdownlint, JSON schema verification against UI spec, cspell, Level 8 Larastan, Pint, FilaCheck), Gate 2 (Gitleaks, policy isolation, write safety), Gate 3 (83 hermetic Pest tests, 90% diff-cover), and Gate 4 (Playwright E2E browser journeys, WCAG 2.1 AA accessibility, and visual snapshot regression).

Running Gate 4 (heavy headless Playwright browser container, database seeding, Chromium startup) on documentation-only pull requests (e.g. `README.md`, `ADRs`, architectural specs) introduces unnecessary build time (2–4 minutes). However, completely skipping CI on documentation changes risks secret leaks, spec-to-code drift, broken JSON schemas, and formatting regressions.

## Decision

We adopt the **ARH Proportionate CI Lane Classification** architecture:

1. **Path-Based Change Classification (`scripts/classify-ci-changes.mjs`)**:
   - Classifies modified surfaces between base and head refs: `php`, `ui`, `docs`.
   - UI surface matches `app/Filament/**`, `resources/**`, `public/**`, `tests/Browser/**`, and `playwright.config.*`.

2. **Always-On Baseline Gates (Zero-Drift Assurance)**:
   - Every pull request and push unconditionally executes:
     - **Gate 1 Docs, Lexicon & Spec Hygiene**: `markdownlint-cli2`, `cspell`, and JSON schema integrity (`docs/ui-spec/navigation-tree.json`, `docs/testing/coverage-risk-matrix.json`).
     - **Gate 2 Security & Policy Preflight**: Gitleaks secret scanner, PII sanitization tests, and sovereign policy isolation assertions.
     - **Gate 1 & Gate 3 Code Gates**: Pint, Level 8 Larastan, FilaCheck, Pest hermetic tests, and 90% diff-coverage.

3. **Proportionate Gate 4 UI Gating**:
   - Gate 4 (`ui-layout-audit`: Playwright E2E, WCAG AA, and visual regression) executes **only** when the `ui` surface is modified (`needs.changes.outputs.ui == 'true'`) or on pushes to `main`/`master`.
   - Docs-only and backend-only PRs bypass Gate 4 browser container spins while remaining 100% verified against regressions, schema invalidations, and spec drift.

## Consequences

- **Positive**: Documentation-only PRs verify in <20 seconds with zero risk of broken schemas, typos, or secret leaks.
- **Positive**: Zero architectural drift: living knowledge doctor and static checks still run on all documentation PRs.
- **Positive**: Full browser and visual regression testing remains strictly guaranteed on merges to `main`/`master`.
