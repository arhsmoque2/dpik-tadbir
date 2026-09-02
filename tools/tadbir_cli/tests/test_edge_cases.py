from __future__ import annotations

from pathlib import Path
from unittest.mock import patch

from tadbir.cli import main
from tadbir.gate import run_gate
from tadbir.pr import triage_pr
from tadbir.snip_runner import CommandResult
from tadbir.status import get_status
from tadbir.triage import triage_tests


def test_status_missing_manifest(tmp_path: Path) -> None:
    (tmp_path / "composer.json").write_text("{}", encoding="utf-8")
    status = get_status(repo_root=tmp_path)
    assert status["manifest_validation"]["valid"] is False
    assert any("not found" in issue for issue in status["manifest_validation"]["issues"])


def test_status_malformed_manifest(tmp_path: Path) -> None:
    (tmp_path / "composer.json").write_text("{}", encoding="utf-8")
    (tmp_path / "arh-project-manifest.json").write_text("{broken json", encoding="utf-8")
    status = get_status(repo_root=tmp_path)
    assert status["manifest_validation"]["valid"] is False
    assert any("Failed to parse" in issue for issue in status["manifest_validation"]["issues"])


def test_gate_missing_binaries(tmp_path: Path) -> None:
    res = run_gate(repo_root=tmp_path)
    assert res["exit_code"] == 1
    assert res["gates"]["pint"]["violations_count"] == 1
    assert res["gates"]["phpstan"]["error_count"] == 1
    assert res["gates"]["pest"]["failed"] == 1


def test_pr_not_found() -> None:
    res_cmd = CommandResult(command=[], exit_code=1, duration_ms=5, stdout="", stderr="PR not found", snipped=False)
    with patch("tadbir.pr.run_command", return_value=res_cmd):
        res = triage_pr(pr_number=999999)
        assert "error" in res


def test_triage_tests_missing_pest(tmp_path: Path) -> None:
    res = triage_tests(repo_root=tmp_path)
    assert "error" in res
    assert res["exit_code"] == 1


def test_cli_subcommands() -> None:
    with patch("tadbir.cli.triage_pr", return_value={"ci_status": {"conclusion": "success"}}):
        assert main(["pr", "34"]) == 0

    with patch("tadbir.cli.triage_pr", return_value={"ci_status": {"conclusion": "failure"}}):
        assert main(["pr", "34"]) == 1

    with patch("tadbir.cli.check_ci", return_value={"conclusion": "success"}):
        assert main(["ci-wait"]) == 0

    with patch("tadbir.cli.check_ci", return_value={"conclusion": "failure"}):
        assert main(["ci-wait"]) == 1

    with patch("tadbir.cli.triage_tests", return_value={"exit_code": 0, "failure_count": 0}):
        assert main(["test-triage"]) == 0
