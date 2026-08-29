# DPIK Tadbir: Quality Gates

## Gate 1: Static Analysis & Style
- **Pint (Code Style)**: Zero formatting errors (`./vendor/bin/pint --test`).
- **Larastan / PHPStan (Type Safety)**: Level 8 strictness (`./vendor/bin/phpstan analyse --level=8`).
- **Dead Code Audit**: Zero unused dependencies or unreachable routes.

## Gate 2: Security & Privacy Invariants
- **Write Safety Verification**: All outbound email actions and mass ticket reassignments require valid approval payloads.
- **Privacy Scoping**: `PersonalNote` and `PersonalTask` models enforce strict `auth()->id()` tenancy policies.
- **Secret Scrubbing**: Zero live API tokens, Graph keys, or SSH credentials in Git commit history or logs.

## Gate 3: Test Suite & Incremental Coverage
- **Local Test Execution**: 100% passing tests under hermetic SQLite profile (`php artisan test`).
- **Diff Coverage (`diff-cover`)**: Minimum 90% branch coverage on changed lines for all PRs.
- **Tier 1 Critical Coverage**: 95-100% coverage on `AgentService` and `SendEmailTool`.

## Gate 4: Build & Container Readiness
- **Frontend Asset Compilation**: Vite production build (`pnpm build`) succeeds with zero bundle budget warnings.
- **Container Preflight**: Multi-stage FrankenPHP container builds cleanly without development dependencies.
