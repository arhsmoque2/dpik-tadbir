# DPIK Tadbir: Agent Operational Guide

## Project Identity
- **Repository**: `arhsmoque2/dpik-tadbir`
- **Stack**: Laravel 12, PHP 8.4, Filament v4, Livewire 3, Tailwind CSS, `laravel/mcp`, `outlook-mcp`.
- **Purpose**: AI-assisted company management command center for the Managing Director.

## Core Rules & Invariants
1. **Separation of Concerns (SoC)**: Respect the progressive lifecycle documents under `docs/`. Never redefine `INTENT.md` or `CAPABILITIES.md` without an ADR.
2. **Tool Execution Authority**: All AI tools must inherit from `Laravel\Mcp\Server\Tool` and register in `App\Services\Agent\ToolRegistry`.
3. **Write Safety**: Destructive/external mutations must stage proposals; human approval is mandatory.
4. **Token Efficiency**: Always pass `concise=True` when running bulk email scans against `outlook-mcp`.
5. **Private Isolation**: `PersonalNote` and `PersonalTask` must always be scoped to `auth()->id()`.
6. **Cloud Sandbox Agent Independence (Pillar 6)**: Ephemeral sandbox agents operate 100% hermetically using in-memory SQLite and mock providers without requiring production secrets.

## Cloud Sandbox Agent Quickstart
When launching in a fresh cloud sandbox (GitHub Codespaces, Claude Code sandbox, Docker):
```bash
# One-touch automated environment bootstrap (injects GH auth to prevent Composer 403s)
bash scripts/setup-sandbox.sh

# Run complete hermetic preflight gate (Pint + PHPStan L8 + Pest + 90% diff-cover)
bash scripts/sandbox-preflight.sh
```

## Standard Commands
```bash
# Quality & Static Analysis
composer check:quick     # Run Pint + FilaCheck + PHPStan L8 + Pest (fast local preflight)
composer test:diff       # Run Pest coverage + enforce 90% diff-cover gate
composer fix             # Auto-fix Pint styles, FilaCheck AST, and Rector types
composer analyse         # Run PHPStan / Larastan Level 8

# MCP & Sync
php artisan mcp:serve    # Run native MCP server endpoint
python -m outlook_mcp    # Run Python Outlook MCP bridge
```
