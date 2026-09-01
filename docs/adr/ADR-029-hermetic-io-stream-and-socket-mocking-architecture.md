# ADR-029: Hermetic I/O Stream & Socket Mocking Architecture for CI Quality Gates

**Status**: Accepted  
**Date**: 2026-09-01  
**Decision Makers**: Lead Architecture Agent, Managing Director  

## Context
Quality Gate 3 mandates strict 90% diff branch coverage (`uvx diff-cover coverage.xml --compare-branch origin/main --fail-under 90`) across all pull requests. Domain services interacting directly with network sockets and protocol streams (such as `MailDiagnosticService` executing `stream_socket_client`, `fgets`, `fwrite`, and `fclose` for IMAP `:993` and SMTP `:465` probes) cannot reliably execute live TCP/SSL connections in hermetic sandbox CI runners. Furthermore, external network dependencies introduce latency, flakiness, and security leaks.

Generic test generators scan Eloquent schemas and CRUD resources, failing to generate context-aware mocks for socket I/O, error handling branches, or custom Filament Page actions (`ExecutiveSettings`). A deterministic, zero-flakiness mocking standard is required.

## Decision
1. **Adoption of Namespace-Level Function Interception & Stream Mocking**:
   - Standardize `php-mock/php-mock-mockery` as a project-level dev dependency to intercept built-in PHP network functions (`stream_socket_client`, `fsockopen`) via PHP's namespace fallback resolution policy without requiring C-extensions (like `runkit7`).
   - Standardize in-memory stream fixtures (`php://temp`, `php://memory`) to deterministically simulate IMAP server greetings (`* OK ...`), authentication challenge/response sequences, and SMTP banner exchanges (`220 ...`, `250 OK`).
2. **Deterministic Livewire & Filament v4 Action Testing Standard**:
   - Utilize Pest's Livewire integration (`livewire(Page::class)`) to directly invoke standalone Filament Page actions (`call('formatJson')`, `call('resetDefaultJson')`, `call('runMailDiagnostics')`), ensuring all UI action handlers, state transformations, and notification dispatches achieve 100% test coverage without headless browser overhead.
3. **Hermetic Test Isolation**:
   - All diagnostic probes and socket failure branches must be exercised inside isolated, hermetic Pest feature tests (`tests/Feature/Mcp/MailDiagnosticServiceTest.php`, `tests/Feature/Filament/ExecutiveSettingsTest.php`), ensuring zero unmocked network egress during CI test runs.

## Consequences
- **Positive**: 100% deterministic test execution in CI with sub-second execution times and zero network flakiness.
- **Positive**: Complete test coverage of critical failure branches (connection timeouts, SSL handshake failures, bad credentials, malformed protocol greetings).
- **Positive**: Eliminates agent token churn by establishing predictable, reusable test recipes for network I/O and custom Filament page actions.
