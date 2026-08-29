# DPIK Tadbir: Test Plan & Verification Matrix

Governed by `arh-test-coverage-advisor` and `arh-quality-cicd-design`.

## 1. Test Architecture & Dual-Profile Strategy

To enable zero-ask sandbox independence, the test suite operates under two profiles:
1. **Hermetic Local Profile (`TEST_DB_PROFILE=sqlite`)**:
   - Uses in-memory SQLite (`:memory:`) for sub-second offline unit/feature testing without external services.
2. **Managed / PR Profile (`TEST_DB_PROFILE=postgres`)**:
   - Uses PostgreSQL container in GitHub Actions to test real relational constraints and concurrency.

## 2. Spec-to-Test Mapping

| Capability / Scenario | Test Suite | Test Type | Acceptance Verification Gate |
| :--- | :--- | :--- | :--- |
| **`[CAP-001]` Agent Loop & Hallucination Guard** | `tests/Feature/Agent/AgentServiceTest.php` | Unit & Feature | Asserts agent forces retry when claiming action without tool use. |
| **`[CAP-002]` Outlook MCP Client Integration** | `tests/Unit/Mcp/OutlookMcpClientTest.php` | Mock / Contract | Validates concise payload transformations, delta queries, error mappings. |
| **`[CAP-003]` Personal Notes Backlinking** | `tests/Feature/Notes/PersonalNoteTest.php` | Integration | Tests markdown storage, tag parsing, and foreign key linking to email thread ID. |
| **`[CAP-004]` Personal Tasks Isolation** | `tests/Feature/Tasks/PersonalTaskPolicyTest.php` | Security / Auth | Verifies User A cannot read/update User B's personal tasks even with admin role. |
| **`[CAP-005]` Project & Staff Workload** | `tests/Feature/Staff/StaffWorkloadTest.php` | Integration | Verifies aggregate calculation of open tickets per position. |
| **`[CAP-006]` Resonator Inbox Threading** | `tests/Feature/Inbox/ResonatorInboxTest.php` | Feature | Verifies message grouping into threads, star toggling, and unread counts. |
| **`[CAP-007]` Write Safety Proposal Gate** | `tests/Feature/Mcp/WriteSafetyProposalTest.php` | Security | Asserts outbound email dispatch fails if approval token is missing or expired. |
| **`[CAP-008]` `/mcp` Server Endpoint** | `tests/Feature/Mcp/McpServerEndpointTest.php` | Protocol / Contract | Verifies JSON-RPC 2.0 schema response against MCP specification. |

## 3. Incremental Quality Gate Rules (`diff-cover`)
- Any PR altering code in `app/Services/Agent/` or `app/Mcp/` must achieve **100% line & branch coverage on changed lines**.
- Global PR threshold is set to **90% coverage on modified lines** (`--fail-under 90`).
