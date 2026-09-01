from __future__ import annotations

import json
from unittest.mock import patch

from tadbir.ci import check_ci
from tadbir.snip_runner import CommandResult


def test_check_ci_success() -> None:
    ci_obj = [{
        "databaseId": 999,
        "status": "completed",
        "conclusion": "success",
        "url": "https://github.com/...",
        "name": "CI",
        "headBranch": "main",
        "createdAt": "2026-09-01T00:00:00Z",
    }]
    ci_json = json.dumps(ci_obj)
    res_cmd = CommandResult(command=[], exit_code=0, duration_ms=10, stdout=ci_json, stderr="", snipped=False)

    with patch("tadbir.ci.run_command", return_value=res_cmd):
        res = check_ci(branch="main")
        assert res["status"] == "completed"
        assert res["conclusion"] == "success"
        assert res["run_id"] == 999


def test_check_ci_failure() -> None:
    ci_obj = [{
        "databaseId": 888,
        "status": "completed",
        "conclusion": "failure",
        "url": "https://github.com/...",
        "name": "CI",
        "headBranch": "feat",
        "createdAt": "2026-09-01T00:00:00Z",
    }]
    ci_json = json.dumps(ci_obj)

    def mock_run(cmd: list[str], *args: object, **kwargs: object) -> CommandResult:
        cmd_str = " ".join(cmd)
        if "run list" in cmd_str:
            return CommandResult(command=cmd, exit_code=0, duration_ms=10, stdout=ci_json, stderr="", snipped=False)
        return CommandResult(
            command=cmd,
            exit_code=0,
            duration_ms=10,
            stdout="X Gate 1 failed",
            stderr="",
            snipped=False,
        )

    with patch("tadbir.ci.run_command", side_effect=mock_run):
        res = check_ci(branch="feat")
        assert res["status"] == "completed"
        assert res["conclusion"] == "failure"
        assert len(res["failed_summary"]) > 0
