# ADR-014: Agent Devtooling, Quality Automation & Deterministic Auto-Fix Cascade

**Status**: Accepted  
**Date**: 2026-08-30  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context

Pull requests in **DPIK Tadbir** are governed by a stringent 5-tier quality gate suite ([`docs/QUALITY-GATES.md`](../QUALITY-GATES.md) and [`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)), including Larastan / PHPStan Level 8 strict typing, Filament v4 AST rules, 100% passing hermetic Pest tests with an incremental **90% diff-cover gate**, and Laravel Pint code style checks.

During initial development cycles (e.g. PR #3, PR #4, PR #5), autonomous agents engaged in costly multi-round repair loops:
1. Pushing code with minor styling differences or un-annotated return types.
2. Waiting for remote CI runs to fail.
3. Consuming 15,000–30,000 tokens reading verbose CI logs and manually patching single-line syntax issues across multiple commits (e.g. Pint braces, reflection imports, Filament v3 `bulkActions` deprecations, missing Pest tests).

This pattern incurs high token burn (~100,000 tokens per feature), extends cycle time, and pollutes git history with micro-fix commits. To make agent development sustainable, fast, and token-efficient, the repository must equip agents with first-class local devtools that auto-fix issues deterministically or scaffold required artifacts on the first pass.

## Decision

1. **Adopt a Local Deterministic Auto-Fix Cascade**:
   - Autonomous agents must run a standardized 5-phase local auto-fix cascade before committing or pushing changes:
     - **Phase 1 (Sanity)**: SQLite database touch, configuration clear.
     - **Phase 2 (AST & Deprecation Repair)**: `filacheck app/Filament --fix --dirty` and `rector process` to automatically resolve Filament v4 method deprecations, PHP 8.4 syntax, and strict typing.
     - **Phase 3 (Formatting & Style)**: `vendor/bin/pint` to deterministically format all PHP code and sort imports.
     - **Phase 4 (Test Scaffolding)**: `php artisan test:generate` to auto-scaffold Pest tests for newly introduced models, resources, or services.
     - **Phase 5 (Local Preflight)**: In-memory SQLite Pest suite (`--coverage-clover`) and `diff-cover` validation against the base branch.

2. **Standardize Tier 1 Core Devtools (`require-dev`)**:
   - **FilaCheck** (`laraveldaily/filacheck`): AST linter and automated fixer for Filament v4/v5 APIs (integrated in PR #5; 17/17 rules enforced).
   - **Rector** (`rector/rector` + `driftingly/rector-laravel`): Automated AST refactoring engine for PHP 8.4 and Laravel 12. Adds return types, docblock generics, and framework refactors automatically to satisfy Larastan Level 8.
   - **Laravel Pint** (`laravel/pint`): Zero-configuration PSR-12 and Laravel code style fixer.
   - **Pest Test Generator** (`gsferro/generate-tests-easy`): Automated Pest test scaffolding to satisfy the 90% diff-cover gate on new components.
   - **Laravel Boost & Filament Blueprint** (`laravel/boost`): Agent guidelines providing idiomatic Filament v4 contracts in LLM prompt context to eliminate hallucinations at authoring time.

3. **Standardize Tier 2 / 3 Specialized Devtools**:
   - **Playwright Pest Recorder** (`ComfyCodersBV/laravel-pest-recorder` / `tranquil-tools`): Playwright codegen bridge to record and generate Pest BrowserTest suites for Gate 4 (UI layout and accessibility).
   - **PHPStan Fixer** (`lukaszzychal/phpstan-fixer`): Log-driven surgical error repair for targeted PHPStan diagnostics.
   - **Pre-Push Sanitizers** (`sophireak/laracheck` / `laravel/doctor`): Local environment and configuration preflight checks (`--no-ai` mode).

4. **Establish Canonical Script Contracts in `composer.json`**:
   - `composer fix`: Single-command cascade execution (`filacheck --fix`, `rector`, `pint`).
   - `composer check:quick`: Fast sub-15s local gate verification (`pint --test`, `filacheck`, `phpstan analyse`, `pest --parallel`).
   - `composer check:full`: Full gate preflight including 90% diff-cover and `composer-unused`.
   - `composer test:diff`: In-memory SQLite test execution + Clover XML generation + `diff-cover` threshold check.

5. **Invariant: Zero Remote CI Round-Trips for Deterministic Violations**:
   - No pull request shall be pushed or updated without passing `composer check:quick` locally. Remote CI serves as an immutable verification barrier, not a debugging console.

## Consequences

- **Positive**: Over 90% reduction in agent token consumption during iterative quality repair loops.
- **Positive**: Complete elimination of trivial CI failures caused by styling (Pint), Filament v3/v4 API deprecations (FilaCheck), or missing return types (PHPStan Level 8).
- **Positive**: Guaranteed satisfaction of the 90% diff-cover gate through automated Pest test scaffolding.
- **Positive**: Clean, readable git history free of iterative trial-and-error micro-fix commits.
- **Trade-off**: Slightly larger `vendor/` directory in development environments, managed safely under Composer `require-dev`.
