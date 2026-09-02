from __future__ import annotations

from tadbir.triage import parse_pest_output


def test_parse_pest_output_all_passing() -> None:
    raw = """
   PASS  Tests\\Feature\\DashboardTest
  ✓ it loads the dashboard                     0.24s
  ✓ it shows executive settings                0.12s

   Tests:    2 passed (2 assertions)
   Duration: 0.36s
"""
    result = parse_pest_output(raw)
    assert result["failure_count"] == 0
    assert "2 passed" in result["summary"]
    assert len(result["failures_by_directory"]) == 0


def test_parse_pest_output_with_failures() -> None:
    raw = """
   PASS  Tests\\Feature\\DashboardTest
  ✓ it loads the dashboard                     0.24s

   FAIL  Tests\\Feature\\Settings\\ImapConfigurationTest
  ⨯ it probes live imap and smtp servers       4.17s

  ───────────────────────────────────────────────
   FAILED  Tests\\Unit\\MailDiagnosticServiceTest
  Failed asserting that two strings are identical.
--- Expected
+++ Actual
@@ @@
-'success'
+'error'

  at tests\\Unit\\MailDiagnosticServiceTest.php:35
     31▕ it('handles smtp probe without credentials returning ready', function () {
  ➜  35▕     expect($result['status'])->toBe('success');

  ───────────────────────────────────────────────
   FAILED  Tests\\Feature\\Settings\\ImapConfigurationTest
  Failed asserting that two strings are identical.

  at tests\\Feature\\Settings\\ImapConfigurationTest.php:62
  ➜  62▕     expect($smtpResult['status'])->toBe('success');

  Tests:    2 failed, 167 passed (823 assertions)
  Duration: 23.69s
"""
    result = parse_pest_output(raw)
    assert result["failure_count"] == 2
    assert "2 failed" in result["summary"]
    assert "tests/Unit" in result["failures_by_directory"]
    assert "tests/Feature/Settings" in result["failures_by_directory"]

    unit_failures = result["failures_by_directory"]["tests/Unit"]
    assert len(unit_failures) == 1
    assert unit_failures[0]["file"] == "MailDiagnosticServiceTest.php"
    assert unit_failures[0]["line"] == 35
