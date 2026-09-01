"""Live status probe and manifest validator for DPIK Tadbir."""

from __future__ import annotations

import json
import re
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from tadbir.snip_runner import run_command


def find_repo_root(start_path: Path | None = None) -> Path:
    """Find repository root containing composer.json and artisan or .git."""
    if start_path is not None:
        return Path(start_path).resolve()

    # Check all parents of this file first
    for parent in Path(__file__).resolve().parents:
        if (parent / "composer.json").is_file() and ((parent / ".git").exists() or (parent / "artisan").is_file()):
            return parent

    candidates = [
        Path.cwd().resolve(),
    ]

    for start in candidates:
        for parent in [start] + list(start.parents):
            if (parent / "composer.json").is_file() and ((parent / ".git").exists() or (parent / "artisan").is_file()):
                return parent

    return candidates[0]


def _snip_state(root: Path) -> dict[str, Any]:
    """Lightweight snip integration check for the cold-start banner: no
    subprocess, just filesystem + trust-store inspection."""
    from tadbir.snip_runner import find_snip_binary

    filters_dir = root / ".snip" / "filters"
    present = sorted(p.stem for p in filters_dir.glob("*.yaml")) if filters_dir.is_dir() else []
    bin_path = find_snip_binary()
    trusted = False
    if bin_path and present:
        import os

        trust_path = Path(os.environ.get("SNIP_CONFIG", str(Path.home() / ".config" / "snip" / "config.toml")))
        trust_store = trust_path.parent / "trusted.json"
        try:
            data = json.loads(trust_store.read_text(encoding="utf-8"))
            trusted = any(str(filters_dir) in k for k in data)
        except Exception:
            trusted = False
    return {
        "installed": bin_path is not None,
        "filters_present": present,
        "trusted": trusted,
        "action": None if (bin_path and trusted) or not present else "run: python tools/tadbir.py snip-setup",
    }


def get_status(repo_root: Path | None = None, fresh: bool = True) -> dict[str, Any]:
    """Execute live, witnessed probes and validate manifest declarations."""
    start_time = time.perf_counter()
    root = find_repo_root(repo_root)

    # 1. Git State Probe
    branch_res = run_command(["git", "rev-parse", "--abbrev-ref", "HEAD"], cwd=root, use_snip=False)
    branch = branch_res.stdout.strip() if branch_res.exit_code == 0 else "unknown"

    status_res = run_command(["git", "status", "--porcelain"], cwd=root, use_snip=False)
    dirty_lines = [line.strip() for line in status_res.stdout.splitlines() if line.strip()]

    log_res = run_command(["git", "log", "-1", "--format=%H|%ai|%s"], cwd=root, use_snip=False)
    last_commit_info = log_res.stdout.strip() if log_res.exit_code == 0 else ""

    ahead_res = run_command(["git", "rev-list", "--count", "@{upstream}..HEAD"], cwd=root, use_snip=False)
    ahead_str = ahead_res.stdout.strip()
    ahead_count = int(ahead_str) if ahead_res.exit_code == 0 and ahead_str.isdigit() else 0

    # 2. CI State Probe via gh — scoped to the current branch so `status` never
    # reports a green run that belongs to an unrelated PR.
    ci_cmd = ["gh", "run", "list", "--limit", "1", "--json",
              "status,conclusion,url,name,databaseId,headBranch,createdAt"]
    if branch and branch != "unknown":
        ci_cmd += ["--branch", branch]
    ci_res = run_command(ci_cmd, cwd=root, use_snip=False)
    ci_data: dict[str, Any] = {}
    if ci_res.exit_code == 0 and ci_res.stdout:
        try:
            parsed = json.loads(ci_res.stdout)
            if isinstance(parsed, list) and len(parsed) > 0:
                ci_data = parsed[0]
        except Exception:
            ci_data = {"raw": ci_res.stdout}

    # 3. Open PRs
    pr_res = run_command(
        ["gh", "pr", "list", "--json", "number,title,state,headRefName,url"],
        cwd=root,
        use_snip=False,
    )
    open_prs: list[dict[str, Any]] = []
    if pr_res.exit_code == 0 and pr_res.stdout:
        try:
            parsed_prs = json.loads(pr_res.stdout)
            if isinstance(parsed_prs, list):
                open_prs = parsed_prs
        except Exception:
            pass

    # 4. Toolchain Health Checks
    pest_bin = root / "vendor" / "bin" / "pest"
    pint_bin = root / "vendor" / "bin" / "pint"
    stan_bin = root / "vendor" / "bin" / "phpstan"

    toolchain_info = {
        "pest": "vendor/bin/pest" if pest_bin.is_file() else None,
        "pint": "vendor/bin/pint" if pint_bin.is_file() else None,
        "phpstan": "vendor/bin/phpstan" if stan_bin.is_file() else None,
    }

    # Clean dirty files (strip status prefixes and normalize)
    clean_dirty: list[str] = []
    for d_line in dirty_lines:
        parts = d_line.split(maxsplit=1)
        if len(parts) == 2:
            clean_dirty.append(parts[0] + " " + parts[1].replace("\\", "/"))
        else:
            clean_dirty.append(d_line.replace("\\", "/"))

    # 5. Manifest Validation
    manifest_path = root / "arh-project-manifest.json"
    manifest_valid = True
    manifest_issues: list[str] = []

    if manifest_path.is_file():
        try:
            with open(manifest_path, encoding="utf-8") as f:
                manifest_data = json.load(f)

            # Check if manifest declares pest but missing
            gates_decl = manifest_data.get("gates", {})
            if "pest" in str(gates_decl).lower() and not pest_bin.is_file():
                manifest_issues.append("Manifest declares pest, but vendor/bin/pest is missing")
                manifest_valid = False

            # Check if manifest declares pint but missing
            if "pint" in str(gates_decl).lower() and not pint_bin.is_file():
                manifest_issues.append("Manifest declares pint, but vendor/bin/pint is missing")
                manifest_valid = False

            # Check phase staleness: manifest names a PR number that no open PR
            # matches while other PRs are open (soft warning, not invalidating).
            phase_info = manifest_data.get("phase", {})
            phase_next = str(phase_info.get("next_action", ""))
            named_prs = set(re.findall(r"PR #(\d+)", phase_next))
            if open_prs and named_prs:
                open_nums = {str(p.get("number")) for p in open_prs}
                if named_prs.isdisjoint(open_nums):
                    manifest_issues.append(
                        f"Manifest phase references PR {sorted(named_prs)}, but open PRs are {sorted(open_nums)}"
                    )
        except Exception as exc:
            manifest_valid = False
            manifest_issues.append(f"Failed to parse arh-project-manifest.json: {exc}")
    else:
        manifest_valid = False
        manifest_issues.append("arh-project-manifest.json not found")

    # 6. Cached Gate Results
    gate_cache_path = root / ".tadbir" / "last-gate-result.json"
    cached_gate = None
    if gate_cache_path.is_file():
        try:
            with open(gate_cache_path, encoding="utf-8") as f:
                cached_gate = json.load(f)
        except Exception:
            pass

    # 7. Continuity State Pointers
    handoff_file = None
    if (root / "handoff.md").is_file():
        handoff_file = "handoff.md"
    elif (root / "docs" / "handoff.md").is_file():
        handoff_file = "docs/handoff.md"

    state_file = "CURRENT_STATE.md" if (root / "CURRENT_STATE.md").is_file() else None

    snip_info = _snip_state(root)

    probe_duration_ms = int((time.perf_counter() - start_time) * 1000)

    payload: dict[str, Any] = {
        "verified_at": datetime.now(timezone.utc).isoformat(),
        "probe_duration_ms": probe_duration_ms,
        "root": root.as_posix(),
        "git": {
            "branch": branch,
            "dirty_count": len(dirty_lines),
            "dirty_files": clean_dirty[:10],
            "ahead": ahead_count,
            "last_commit": last_commit_info,
        },
        "ci": ci_data,
        "open_prs": open_prs,
        "toolchain": toolchain_info,
        "manifest_validation": {
            "valid": manifest_valid,
            "issues": manifest_issues,
        },
        "continuity": {
            "handoff_file": handoff_file,
            "state_file": state_file,
        },
        "snip": snip_info,
    }

    if cached_gate:
        payload["local_gates_cached"] = cached_gate

    return payload
