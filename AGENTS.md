# DPIK Tadbir: Agent Operational Guide

## Project Identity
- **Repository**: `D:\_ARH-AGENT-OS\projects\dpik-tadbir`
- **Stack**: Laravel 12, PHP 8.4, Filament v4, Livewire 3, Tailwind CSS, `laravel/mcp`, `outlook-mcp`.
- **Purpose**: AI-assisted company management command center for the Managing Director.

## Core Rules & Invariants
1. **Separation of Concerns (SoC)**: Respect the progressive lifecycle documents under `docs/`. Never redefine `INTENT.md` or `CAPABILITIES.md` without an ADR.
2. **Tool Execution Authority**: All AI tools must inherit from `Laravel\Mcp\Server\Tool` and register in `App\Services\Agent\ToolRegistry`.
3. **Write Safety**: Destructive/external mutations must stage proposals; human approval is mandatory.
4. **Token Efficiency**: Always pass `concise=True` when running bulk email scans against `outlook-mcp`.
5. **Private Isolation**: `PersonalNote` and `PersonalTask` must always be scoped to `auth()->id()`.

## Standard Commands
```bash
# Quality & Static Analysis
composer analyse         # Run PHPStan / Larastan
composer format          # Run Laravel Pint formatter
php artisan test         # Run Pest / PHPUnit test suite

# MCP & Sync
php artisan mcp:serve    # Run native MCP server endpoint
python -m outlook_mcp    # Run Python Outlook MCP bridge
```
