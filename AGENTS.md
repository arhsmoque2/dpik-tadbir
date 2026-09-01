# DPIK Tadbir: Agent Operational Guide

## Project Identity
- **Repository**: `arhsmoque2/dpik-tadbir`
- **Stack**: Laravel 12, PHP 8.4, Filament v4, Livewire 3, Tailwind CSS, `laravel/mcp`, `outlook-mcp`.
- **Purpose**: AI-assisted company management command center for the Managing Director.

## Core Rules & Invariants
1. **Separation of Concerns (SoC)**: Respect the progressive lifecycle documents under `docs/`. Never redefine `INTENT.md` or `CAPABILITIES.md` without an ADR.
2. **Tool Execution Authority**: All AI tools must inherit from `Laravel\Mcp\Server\Tool` and register in `App\Mcp\ToolRegistry` (`app/Mcp/ToolRegistry.php`).
3. **Write Safety**: Destructive/external mutations must stage proposals; human approval is mandatory.
4. **Token Efficiency**: Always pass `concise=True` when running bulk email scans against `outlook-mcp`.
5. **Private Isolation**: `PersonalNote` and `PersonalTask` must always be scoped to `auth()->id()`.
6. **Cloud Sandbox Agent Independence (Pillar 6)**: Ephemeral sandbox agents operate 100% hermetically using in-memory SQLite and mock providers without requiring production secrets.

## 🚨 Mandatory Agent Cold-Start Protocol
Regardless of your assigned task (investigating a PR, fixing a bug, adding a feature, or running tests), your **FIRST action** in this repository must be running the deterministic control plane:

```bash
# 1. Full live state probe (git, branch-scoped CI, toolchain, manifest, snip state)
python tools/tadbir.py status        # or: composer tadbir:status

# 2. First checkout on this machine (or after editing .snip/filters/): register the
#    snip output filters. status.snip.action tells you when this is needed.
python tools/tadbir.py snip-setup

# 3. If assigned to investigate a PR:
python tools/tadbir.py pr <N>

# 4. Before git push — fast local subset (Pint, PHPStan @ phpstan.neon level, Pest):
python tools/tadbir.py gate
```

> **Rules**:
> - Do not run 15-turn discovery loops or busy-poll `gh run view`. `tadbir` emits verified machine-parseable JSON in one shot.
> - `gate` returning `exit_code: 0` clears the fast subset only. `composer check:full` (FilaCheck, composer-unused, diff-cover ≥ 90) is the authoritative pre-merge gate and CI runs the full set — a green `gate` is necessary, not sufficient.
> - `tadbir pr` / `ci-wait` exit `0` while CI is still running; a non-zero exit means a real failing conclusion.

## Cloud Sandbox Agent Quickstart
When launching in a fresh cloud sandbox (GitHub Codespaces, Claude Code sandbox, Docker):
```bash
# One-touch automated environment bootstrap (injects GH auth to prevent Composer 403s)
bash scripts/setup-sandbox.sh

# Run complete hermetic preflight gate (Pint + PHPStan L8 + Pest + 90% diff-cover)
bash scripts/sandbox-preflight.sh
```

**Re-run `bash scripts/sandbox-preflight.sh` before every push that touches PHP, not just once at session start.** It enforces the same 90% incremental diff-cover gate as CI's own Gate 3 — if it passes locally, Gate 3 passes remotely too. Two real PRs (the OpenRouter/ADR-018 port and the ARH-URUS AI-chat port) each burned 3-5 CI round-trips iterating blind against this gate because `composer install` failed in-session and nobody re-tried hydration before pushing anyway. If plain `composer install` fails with a GitHub API auth error (`Could not authenticate against github.com` — common in ephemeral sandboxes, not a real credentials problem), do **not** give up on local verification for the rest of the session: `bash scripts/setup-sandbox.sh` already falls back to a pre-compiled `vendor.tar.gz` release bundle (Tier 1, <3s, bypasses the API limit entirely) before it ever tries `composer install` — run it again, or unpack the bundle directly:
```bash
curl -sL https://github.com/arhsmoque2/dpik-tadbir/releases/download/sandbox-vendor-latest/vendor.tar.gz | tar -xz
```
Only fall back to `DEVTOOLS.md`'s Degraded CI-Feedback Protocol (push and read CI logs) if this bundle download itself fails — that's a real air-gapped sandbox, not a GitHub API rate limit.

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
