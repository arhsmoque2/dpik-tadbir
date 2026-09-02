"""CI status check and wait helper."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from tadbir.snip_runner import run_command
from tadbir.status import find_repo_root


def check_ci(branch: str | None = None, repo_root: Path | None = None) -> dict[str, Any]:
    """Single-shot check of latest CI run for branch without busy-waiting."""
    root = find_repo_root(repo_root)

    if not branch:
        branch_res = run_command(["git", "rev-parse", "--abbrev-ref", "HEAD"], cwd=root, use_snip=False)
        branch = branch_res.stdout.strip() if branch_res.exit_code == 0 else ""

    cmd = ["gh", "run", "list", "--limit", "1", "--json", "status,conclusion,url,name,databaseId,headBranch,createdAt"]
    if branch:
        cmd.extend(["--branch", branch])

    ci_res = run_command(cmd, cwd=root, use_snip=False)
    if ci_res.exit_code != 0 or not ci_res.stdout:
        return {"status": "error", "message": ci_res.stderr or "No CI runs found"}

    try:
        parsed = json.loads(ci_res.stdout)
        if isinstance(parsed, list) and len(parsed) > 0:
            run_data = parsed[0]
            run_id = run_data.get("databaseId")
            status = run_data.get("status")
            conclusion = run_data.get("conclusion")

            # If completed with failure, fetch condensed failure summary
            failed_summary = []
            if status == "completed" and conclusion == "failure" and run_id:
                view_res = run_command(["gh", "run", "view", str(run_id)], cwd=root, use_snip=True)
                failed_summary = [line.strip() for line in view_res.stdout.splitlines() if line.strip()][:15]

            return {
                "status": status,
                "conclusion": conclusion,
                "run_id": run_id,
                "url": run_data.get("url"),
                "name": run_data.get("name"),
                "branch": run_data.get("headBranch"),
                "created_at": run_data.get("createdAt"),
                "failed_summary": failed_summary,
            }
    except Exception as exc:
        return {"status": "error", "message": f"Failed to parse CI response: {exc}"}

    return {"status": "unknown"}
