# DPIK Tadbir: Test Plan & Verification Matrix

Governed by `arh-test-coverage-advisor` and `arh-quality-cicd-design`.

## 1. Test Architecture & Dual-Profile Strategy

To enable zero-ask sandbox independence and hermetic verification, the test suite operates under two profiles:
1. **Hermetic Local Profile (`TEST_DB_PROFILE=sqlite`)**:
   - Uses in-memory SQLite (`:memory:`) with parallel Pest test runner for sub-second offline unit, feature, and policy testing without external services.
2. **Managed / PR Profile (`TEST_DB_PROFILE=postgres`)**:
   - Uses PostgreSQL container in GitHub Actions to test real relational constraints, concurrency locks, and trigger behaviors.

---

## 2. Spec-to-Test Mapping

| Capability / Scenario | Test Suite | Test Type | Acceptance Verification Gate |
| :--- | :--- | :--- | :--- |
| **`[CAP-001]` Agent Loop & Hallucination Guard** | `tests/Feature/Ai/AgentServiceTest.php` | Unit & Feature | Asserts agent forces retry when claiming action without `AiActionReceipt`. |
| **`[CAP-002]` Outlook MCP Bridge Client** | `tests/Unit/Mcp/OutlookMcpBridgeTest.php` | Mock / Contract | Validates concise payload transformations, delta queries, token expiry handling. |
| **`[CAP-003]` Executive Presets & Smart Email Scans** | `tests/Feature/Presets/PresetExecutionServiceTest.php` | Feature | Asserts dynamic prompt interpolation and instant delta scan aggregation. |
| **`[CAP-004]` Write Safety Proposal Gate** | `tests/Feature/Mcp/WriteSafetyProposalTest.php` | Security / Gate | Asserts outbound email dispatch fails if approval token is missing, forged, or expired. |
| **`[CAP-005]` Project Register Continuous Memory** | `tests/Feature/Memory/ProjectRegisterTest.php` | Integration | Tests markdown storage, tag parsing, and FTS5 synchronization triggers on save. |
| **`[CAP-006]` Action Memory & Rolling Audit Summaries** | `tests/Feature/Audit/ActionMemoryServiceTest.php` | Feature | Verifies immutable receipt logging and rollup generation. |
| **`[CAP-007]` Personal Notes & Tasks Isolation** | `tests/Feature/Notes/PersonalNotePolicyTest.php`<br>`tests/Feature/Tasks/PersonalTaskPolicyTest.php` | Security / Auth | Verifies User A cannot read/update User B's personal notes or tasks. |
| **`[CAP-008]` Project & Staff Oversight Engine** | `tests/Feature/Projects/ProjectOversightTest.php`<br>`tests/Feature/Staff/StaffWorkloadTest.php` | Integration | Verifies aggregate calculation of open tickets and ticket reassignment safety. |
| **`[CAP-009]` Visual Command Center Dashboard** | `tests/Feature/Filament/DashboardWidgetsTest.php` | Livewire / Feature | Verifies rendering of KPI widgets and pending action cards queue. |
| **`[CAP-010]` Multi-Role RBAC & MCP Server Endpoint** | `tests/Feature/Mcp/McpServerEndpointTest.php`<br>`tests/Feature/Auth/MultiRolePolicyTest.php` | Protocol / Security | Verifies JSON-RPC 2.0 schema response and role-based endpoint permissions. |
| **`[CAP-011]` Hybrid FTS5 BM25 Lexical Search** | `tests/Unit/Memory/Fts5LexicalSearchTest.php` | Integration | Asserts sub-millisecond lexical full-text search across virtual tables. |
| **`[CAP-012]` Decision Marker Heuristics (`dm:hit`)** | `tests/Unit/Memory/DecisionMarkerExtractorTest.php` | Unit | Asserts accurate extraction of `dm:decision`, `dm:commitment`, and `dm:blocker`. |
| **`[CAP-013]` Reciprocal Rank Fusion (RRF)** | `tests/Unit/Memory/RrfRerankerTest.php` | Unit | Asserts mathematical correctness of $RRF = \sum \frac{1}{60 + \text{rank}}$ and recency decay. |
| **`[CAP-014]` Dense Context Formatter** | `tests/Unit/Memory/DenseContextFormatterTest.php` | Unit | Verifies token-dense pipe-delimited context formatting under token ceiling. |
| **`[CAP-015]` Executive Personalization Engine** | `tests/Feature/Ai/PersonalizationReflectionTest.php` | Feature | Verifies weekly profile reflection and prompt injection. |
| **`[CAP-016]` Interactive Modals & Escape Hatches** | `tests/Feature/Interactive/AskUserQuestionToolTest.php` | Feature / State | Asserts execution loop enters `AWAITING_USER_INPUT` and resumes cleanly. |

---

## 3. Incremental Quality Gate Rules (`diff-cover`)

- Any PR altering code in Tier 1 (`app/Services/Ai/`, `app/Services/Mcp/`, `app/Mcp/`, `app/Policies/`) must achieve **95–100% branch coverage on changed lines**.
- Global PR threshold is set to **90% coverage on modified lines** (`--fail-under 90`).
- Gate 2 fail-closed security assertions (`WriteSafetyProposalTest`, `PersonalNotePolicyTest`, `PersonalTaskPolicyTest`) are mandatory hard blockers for merge.
