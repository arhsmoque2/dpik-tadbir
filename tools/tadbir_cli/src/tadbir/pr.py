"""PR investigation and CI triage module."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from tadbir.path_utils import strip_root_from_text
from tadbir.snip_runner import run_command
from tadbir.status import find_repo_root


def triage_pr(pr_number: int | None = None, repo_root: Path | None = None) -> dict[str, Any]:
    """Triage a Pull Request: branch, CI status, failing gate, and exact failure lines."""
    root = find_repo_root(repo_root)

    # 1. Fetch PR details
    if pr_number is not None:
        pr_cmd = ["gh", "pr", "view", str(pr_number), "--json", "number,title,state,headRefName,url"]
    else:
        pr_cmd = ["gh", "pr", "list", "--limit", "1", "--json", "number,title,state,headRefName,url"]

    pr_res = run_command(pr_cmd, cwd=root, use_snip=False)
    pr_data: dict[str, Any] = {}

    if pr_res.exit_code == 0 and pr_res.stdout:
        try:
            parsed = json.loads(pr_res.stdout)
            if isinstance(parsed, list) and len(parsed) > 0:
                pr_data = parsed[0]
            elif isinstance(parsed, dict):
                pr_data = parsed
        except Exception:
            pr_data = {"raw": pr_res.stdout}

    if not pr_data:
        return {
            "error": f"Could not find PR details for #{pr_number}" if pr_number else "No open PRs found",
            "exit_code": 1,
        }

    branch = pr_data.get("headRefName", "")
    target_pr_num = pr_data.get("number", pr_number)

    # 2. Get latest CI run for this branch
    ci_res = run_command(
        ["gh", "run", "list", "--branch", branch, "--limit", "1", "--json", "status,conclusion,url,name,databaseId"],
        cwd=root,
        use_snip=False,
    )
    ci_info: dict[str, Any] = {}
    run_id: int | None = None
    conclusion = "unknown"

    if ci_res.exit_code == 0 and ci_res.stdout:
        try:
            ci_list = json.loads(ci_res.stdout)
            if isinstance(ci_list, list) and len(ci_list) > 0:
                ci_info = ci_list[0]
                run_id = ci_info.get("databaseId")
                conclusion = ci_info.get("conclusion", "in_progress")
        except Exception:
            pass

    # 3. If failed, fetch failure details with snip
    failure_details: list[str] = []
    failed_job = ""
    failed_step = ""

    if run_id and conclusion == "failure":
        # Get run summary
        view_res = run_command(["gh", "run", "view", str(run_id)], cwd=root, use_snip=True)
        for line in view_res.stdout.splitlines():
            if line.startswith("X ") or ("Gate " in line and ("fail" in line.lower() or "X " in line)):
                if not failed_job and "Gate" in line:
                    failed_job = line.strip()

        # Get failure log snippet
        log_res = run_command(["gh", "run", "view", str(run_id), "--log-failed"], cwd=root, use_snip=True)
        if log_res.stdout:
            failure_details = [strip_root_from_text(line, root) for line in log_res.stdout.splitlines() if line.strip()]

    actionable_step = "All CI checks passing."
    if conclusion == "failure":
        if any("Coverage is below" in line or "Missing lines" in line for line in failure_details):
            actionable_step = "Diff coverage below threshold. Add unit tests covering the missing lines reported above."
        elif any("FAIL" in line or "FAILED" in line for line in failure_details):
            actionable_step = "Pest or unit tests failed in CI. Reproduce locally using 'python tools/tadbir.py gate'."
        else:
            actionable_step = f"CI run {run_id} failed. Run 'python tools/tadbir.py gate' locally to verify and fix."

    return {
        "pr": {
            "number": target_pr_num,
            "title": pr_data.get("title"),
            "branch": branch,
            "state": pr_data.get("state"),
            "url": pr_data.get("url"),
        },
        "ci_status": {
            "run_id": run_id,
            "conclusion": conclusion,
            "failed_job": failed_job,
            "failed_step": failed_step,
            "failure_details": failure_details[:20],
        },
        "local_reproduction": {
            "command": "python tools/tadbir.py gate",
            "actionable_next_step": actionable_step,
        },
    }
