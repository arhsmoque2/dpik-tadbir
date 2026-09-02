# PR-006: Session Telemetry & Token Churn Analysis (The Genesis of Tadbir CLI)

> **Context**: Empirical findings derived from 54 historical agent sessions across Kimi, Claude Code, Codex, and Antigravity operating on `arhsmoque2/dpik-tadbir`.

---

## Part 1 — Interpretation of Session Telemetry Findings

### The Core Problem Is Behavioral, Not Technical

The 54-session telemetry corpus reveals something critical: **the dpik-tadbir codebase itself was not broken**. The quality gates work. Pest runs. Pint formats. Larastan catches real issues. Cloud Run deploys. The project manifest exists and is well-structured. The CI pipeline is correctly tiered (blocking Tier 1, non-blocking E2E).

**The problem is that agents did not use what already existed.** Every single one of the recursive patterns identified in the telemetry shares a common root cause: the agent enters the session *cold*, spends 15–30 tool calls rediscovering the project state, then bypasses local quality gates and pushes directly to GitHub, converting the remote CI pipeline into an expensive real-time feedback loop.

### Empirical Breakdown

#### 1. Over 59% of the Total Token Budget Was Spent Waiting
- CI/CD polling consumed **10.35 MB** out of ~17.5 MB total — **59% of all dpik-tadbir token spend** was an agent running `gh run view <id>` in a tight loop, waiting for GitHub Actions to report what it could have verified locally in 4 seconds by running `composer run test`.

#### 2. Context Gathering Is the Second-Largest Expense
- The `FILE_READ → FILE_READ` chain (893 occurrences) and `FILE_READ → FILE_READ → FILE_READ` (524 occurrences) represented repeated reconnaissance across sessions. Because there was no single-shot snapshot, agents re-read the same 15+ files across every cold boot.

#### 3. The "One-Test-at-a-Time" Anti-Pattern
- In session `a722ba8c`, an agent ran Pest 41 times, fixing one assertion per run. It lacked a batch failure harness that executes the suite once, clusters failures by root cause, and guides holistic remediation.

#### 4. The Three Structural Gaps

| Gap | What Agents Did Manually | What Was Needed |
| :--- | :--- | :--- |
| **Cold-Start Recon** | Ran 15–30 discovery commands (list_dir, view_file, grep, git log) | Read a single pre-computed status JSON |
| **Gate Orchestration** | Ran gates piecemeal (Pint alone, then Pest alone), push after each, poll CI | Run all local gates atomically in one call, get machine-parseable state, push only when green |
| **CI Wait Strategy** | Tight-loop `gh run view` every 15 seconds (up to 64 times per session) | Fire-and-forget push, single-shot check |

---

## Part 2 — The Proposed `tadbir` Control Plane

### Design Philosophy
A project-specific composed workflow toolkit whose every command directly eliminates a measured telemetry anti-pattern.

### Planned Command Index

| Command | Targets Pattern | Token Savings Estimate | Description |
| :--- | :--- | :--- | :--- |
| `tadbir status` | Context Gathering (893x FILE_READ loops) | ~3,000–4,000 tokens/session | One-shot project snapshot for agent cold-start |
| `tadbir gate` | CI Polling + Pest Loops (765x GH_RUN_POLL + 416x read-edit-verify) | ~50,000–200,000 tokens/session | Run all local quality gates atomically, report JSON |
| `tadbir pr` | Push-then-poll anti-pattern (672 GH polls) | ~30,000–100,000 tokens/session | One-shot PR state, CI failure step, and missing coverage diffs |
| `tadbir ci-wait` | GH_RUN_POLL busy-waiting (619x triple-poll chains) | ~20,000–50,000 tokens/session | Fire one `gh run list`, return current status, no polling loop |
| `tadbir test-triage` | One-at-a-time Pest fixing (41 runs in one session) | ~10,000–30,000 tokens/session | Run full Pest suite, collect ALL failures, emit structured triage |

### Expected Impact

| Metric | Before (per session avg) | After (projected) | Reduction |
| :--- | :--- | :--- | :--- |
| Cold-start context gathering | 15–30 tool calls, 4,000 tokens | 1 call, ~240 tokens | **~94%** |
| CI polling calls per push | 10–64 `gh run view` | 1 `tadbir ci-wait` | **~95%** |
| Test-fix iterations per session | 5–41 pest runs | 1 triage + 1–3 fix cycles | **~75%** |
| Average session footprint | ~325 KB (~81k tokens) | ~100–150 KB (~25–37k tokens) | **~55–70%** |
| Push-without-local-gate rate | ~70% of sessions | 0% (enforced by `tadbir gate`) | **100%** |
