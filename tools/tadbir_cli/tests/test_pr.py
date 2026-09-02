from __future__ import annotations

from unittest.mock import patch

from tadbir.pr import triage_pr
from tadbir.snip_runner import CommandResult


def test_triage_pr_mocked_failure() -> None:
    pr_json = (
        '{"number": 34, "title": "feat: test pr", "state": "OPEN", '
        '"headRefName": "feat/branch", "url": "https://github.com/..."}'
    )
    ci_json = '[{"databaseId": 12345, "status": "completed", "conclusion": "failure", "headBranch": "feat/branch"}]'

    def mock_run(cmd: list[str], *args: object, **kwargs: object) -> CommandResult:
        cmd_str = " ".join(cmd)
        if "pr view" in cmd_str or "pr list" in cmd_str:
            return CommandResult(command=cmd, exit_code=0, duration_ms=10, stdout=pr_json, stderr="", snipped=False)
        elif "run list" in cmd_str:
            return CommandResult(command=cmd, exit_code=0, duration_ms=10, stdout=ci_json, stderr="", snipped=False)
        elif "--log-failed" in cmd_str:
            out = "Coverage is below 90%.\nMissing lines 55-56"
            return CommandResult(command=cmd, exit_code=0, duration_ms=10, stdout=out, stderr="", snipped=False)
        else:
            return CommandResult(
                command=cmd,
                exit_code=0,
                duration_ms=10,
                stdout="X Gate 3: Hermetic Tests",
                stderr="",
                snipped=False,
            )

    with patch("tadbir.pr.run_command", side_effect=mock_run):
        res = triage_pr(pr_number=34)
        assert res["pr"]["number"] == 34
        assert res["ci_status"]["conclusion"] == "failure"
        assert len(res["ci_status"]["failure_details"]) > 0
        assert "Diff coverage below threshold" in res["local_reproduction"]["actionable_next_step"]
