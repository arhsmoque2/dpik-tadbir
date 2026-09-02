# PR-003: Relaticle Architecture Patterns & Calm Enterprise UI/UX Paradigm

- **Date**: 2026-09-02
- **Focus**: Extraction of proven patterns from Relaticle CRM, Surface Token Architecture, and Mitigation of "AI Cockpit/Gadget" Clutter.
- **Target**: DPIK Tadbir (Laravel 12 / Filament v4 / Livewire 3).

---

## 1. Context & UI/UX Design Problem

Modern AI applications frequently suffer from **"Over-Instrumented Cockpit Syndrome"**:
* Interfaces display dozens of floating telemetry gauges, token counters, latency dials, and model logos simultaneously.
* The visual design becomes noisy, chaotic, and resembles a toy/gadget dashboard rather than a calm, authoritative executive workstation.
* Senior leadership (Managing Director, Senior Partners) requires a clean, focused, and distraction-free decision environment.

---

## 2. The 4-Tier Surface Design Token System

Relaticle solves visual noise through a **4-tier muted surface hierarchy**:

```
Layer 1: Canvas Wash     (rgb(248 249 250) / dark rgb(15 17 21))
Layer 2: Shell Divider   (Hairline 1px borders rgb(229 231 235))
Layer 3: Translucent Card (90% alpha pills and floating chips)
Layer 4: Solid Data Block (Pure white #ffffff / dark rgb(22 25 31))
```

### Typographic Restraint
* Off-step micro tokens (`--text-micro: 11px`, `--text-pico: 10px`) for secondary shortcuts and metadata.
* High-density tabular decision registers replacing circular HUD meters.

---

## 3. Core Architectural Takeaways

1. **Strict Action Confinement (`EloquentWriteOutsideActionRule`)**: Eliminates side-effects in Livewire/Controllers by enforcing single-entry Actions.
2. **Atomic AI Plan Chaining (`PlanReferenceResolver`)**: Allows multi-entity dependent creation in one conversational turn using `$ref:<id>`.
3. **Tenant Sovereign Validation (`TenantFkValidator`)**: Guarantees zero cross-tenant entity leakage.
