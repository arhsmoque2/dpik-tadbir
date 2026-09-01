# ADR-022: Bundle-Based Retrieval, AI-Optional Review & Adaptive Navigation

**Status**: Accepted
**Date**: 2026-09-01
**Decision Makers**: Managing Director, Lead Architecture Agent

---

## Context

Design review of the executive dashboard (via a sequence of page-by-page HTML previews, capped against a real DPIK brand reference — the DPI Konsult "Staff Portal") surfaced a product-shape problem, not a visual one: the panel had been designed around Copilot chat as the *only* entry point into retrieved mail. `docs/UI.md`'s `UI-11` dashboard composition (stat cards, widget rows) and the original Copilot-drawer-first flow assumed the executive would always ask the AI to mediate access to their inbox.

The Managing Director's direction was explicit: *"the purpose is to reduce total dependency on AI chat — when a bundle is retrieved, the user may choose to read it themselves and write notes about it without AI; AI however can do this too."* This is a real constraint on every future feature, not a one-off UI tweak, and it directly narrows [`INTENT-03`](../INTENT.md#intent-03-core-operating-principles)'s existing principles rather than adding an unrelated one — hence an ADR rather than a silent UI pass, per this repo's own rule (`AGENTS.md` §1: never redefine `INTENT.md`/`CAPABILITIES.md` without an ADR).

Two supporting decisions came out of the same review pass:
1. **What does a retrieval actually produce?** Previously, a retrieval's output existed only as ephemeral LLM context. Once AI is optional, retrieved mail needs to be a durable, nameable, human-facing object in its own right — this ADR names that object a **Bundle**.
2. **How does navigation change once the dashboard is a Bundle/Session list rather than a metrics board, and once mobile drops the desktop nav rail?** The mobile bottom bar needed a fixed anchor for Copilot (so it never disappears or gets buried) while staying flexible enough for an executive to prioritize the destinations they actually use.

---

## Decision

1. **Every retrieval is materialized as a Bundle.**
   A Bundle is a durable, named record of the emails matched by one filter execution (`Bundle 1`, `Bundle 2`, ...) — not merely a chat context window. A Bundle is independently readable: each retrieved email can be opened and read inline, and the executive can write free-text notes directly on the Bundle. Naming considered and rejected: "batch" (reads as a technical/back-office term, works against the calm-executive tone in [`UI-01`](../UI.md)); "session" (already means an AI chat session in this product — reusing it would conflate the human-first object with the AI-mediated one, exactly the confusion this ADR exists to resolve).

2. **Default retrieval filter, applied whenever no preset is explicitly chosen:**
   - Sent **today**.
   - Addressed **directly** to the executive (`To:`), excluding `CC:`.
   - **New since the last retrieval only** — a delta fetch; read/unread status is not part of the filter.

   Executives may select alternate presets (last 48 hours, a specific project code, include CC, etc.) from a filter picker attached to the Retrieve control; whichever filter is active is always visibly named at the point of retrieval (a small chip on the nav bar, not only inside a picker sheet).

3. **Auto-promotion of high-signal project filters.**
   Once a single project accounts for more than 3 sessions or Bundles within a rolling 7-day window, it is automatically surfaced as a quick filter — no manual setup. This keeps the filter rail relevant to what the executive is actually working on without asking them to curate it. Demotion is intentionally left unspecified for now (see Consequences) rather than over-specified against a hypothetical.

4. **Copilot is an optional action layered on a Bundle, never the entry point.**
   Every Bundle screen exposes an explicit "Ask Copilot about this Bundle" action (preset prompts: summarize the Bundle, draft a reply, find a specific email), but reading a Bundle and writing notes on it requires zero AI involvement. This reverses the prior implicit assumption that Copilot chat mediates all retrieved-mail access.

5. **Dashboard/Sessions view stays a list, not a metrics board.**
   The default panel view is a Bundle/Session list — AI model, provider, retrieval timestamp, source Bundle, report-exists indicator. Stat-card widgets are not part of the default and are added only once they trace to a concrete `INTENT.md`/`SCENARIOS.md` need. This narrows [`CAP-009`](../CAPABILITIES.md#cap-009-executive-ai-command-center--visual-panel-filament-v4)'s prior "metric widgets" framing.

6. **Adaptive mobile navigation with a fixed Copilot anchor.**
   On mobile/TWA, the primary nav rail ([`UI-03`](../UI.md)) is replaced by a bottom bar. Copilot occupies a fixed center slot on every configuration — never reassignable, never hideable. The remaining four slots are user-customizable in both order and visibility across six candidate destinations (Home, Retrieve, Sessions, Bundles, Notes, Projects, Settings); once more destinations are enabled than slots available, the lowest-priority ones collapse behind a `More (•••)` overflow drawer. Home itself becomes an expandable menu (tapping it reveals the full destination set) rather than a fixed single-page shortcut.

---

## Consequences

- **Positive**: Directly honors the product's own stated purpose — the app should feel like a real, human-owned inbox-review tool that AI assists, not an AI chat product that happens to touch email. This is now written down where it will be checked, not just implied by a screenshot.
- **Positive**: Bundle naming disambiguates "AI chat session" from "a retrieval's results" — a distinction that was previously blurred and would have compounded as more AI-adjacent objects were added.
- **Positive**: Auto-promotion means the filter rail earns relevance from real usage rather than requiring upfront configuration, consistent with the "expansion must earn its place" posture already established for the dashboard.
- **Positive**: Complete doc-chain synchronization across [`docs/INTENT.md`](../INTENT.md), [`docs/CAPABILITIES.md`](../CAPABILITIES.md) (new `CAP-019`, `CAP-020`; narrowed `CAP-009`), and [`docs/SCENARIOS.md`](../SCENARIOS.md) (revised `SCEN-01`; new `SCEN-08`, `SCEN-09`).
- **Trade-off / open question**: the auto-promotion rule specifies *when* a project filter appears but not when it disappears (a project that cools off after its 3-in-7-days threshold is not force-demoted). This is a deliberate deferral, not an oversight — resolve it against real usage data before hard-coding a decay rule.
- **Trade-off**: `docs/UI.md`'s concrete screen/layout sections (`UI-03`, `UI-05`, `UI-11`) now describe the pre-Bundle, chat-first dashboard and are stale against this decision and the delivered HTML previews. This ADR does not rewrite `UI.md` itself — see the companion note below on `docs/ui-spec/` as the living, auditable design surface; a follow-up pass should refresh `docs/ui-spec/mockup-preview.html` and `navigation-tree.json` to match, and slim `UI.md` down to the rationale/token/accessibility content that isn't well expressed as a screen (an explicit follow-up, not silently done here).
- **Out of scope here**: PDF export/download/share of a generated report (raised in the same design review as a `CAP-006` extension) is not designed yet and is intentionally left for its own future ADR rather than bundled into this one.
