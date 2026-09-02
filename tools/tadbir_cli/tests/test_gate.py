from __future__ import annotations

from unittest.mock import patch

from tadbir.gate import parse_pest_metrics, parse_phpstan_metrics, parse_pint_metrics, run_gate
from tadbir.snip_runner import CommandResult


def test_parse_pest_metrics() -> None:
    raw = "Tests: 2 failed, 185 passed, 1 skipped (860 assertions)"
    metrics = parse_pest_metrics(raw)
    assert metrics["total_tests"] == 188
    assert metrics["passed"] == 185
    assert metrics["failed"] == 2
    assert metrics["skipped"] == 1
    assert metrics["assertions"] == 860


def test_parse_phpstan_metrics() -> None:
    raw = "Found 3 errors\nLine 42: Undefined variable $foo"
    metrics = parse_phpstan_metrics(raw)
    assert metrics["error_count"] == 3
    assert len(metrics["errors"]) > 0


def test_parse_pint_metrics() -> None:
    raw = '{"tool":"pint","result":"passed"}'
    metrics = parse_pint_metrics(raw)
    assert metrics["violations_count"] == 0
    assert len(metrics["violations"]) == 0


def test_run_gate_structure() -> None:
    mock_res = CommandResult(
        command=["mock"],
        exit_code=0,
        duration_ms=10,
        stdout="Tests: 187 passed (860 assertions)",
        stderr="",
        snipped=False,
    )

    with patch("tadbir.gate.run_command", return_value=mock_res), patch("pathlib.Path.is_file", return_value=True):
        gate_res = run_gate()
        assert "verified_at" in gate_res
        assert "duration_seconds" in gate_res
        assert gate_res["exit_code"] == 0
        assert "pint" in gate_res["gates"]
        assert "phpstan" in gate_res["gates"]
        assert "pest" in gate_res["gates"]
        assert gate_res["gates"]["pest"]["passed"] == 187
        assert gate_res["gates"]["pest"]["assertions"] == 860
