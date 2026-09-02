"""Pest failure batch triage and root-cause cluster analyzer with path compression."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any

from tadbir.path_utils import collapse_by_parent_dir, strip_root_from_text, to_relative_posix
from tadbir.snip_runner import run_command
from tadbir.status import find_repo_root


def parse_pest_output(raw_output: str, repo_root: Path | str | None = None) -> dict[str, Any]:
    """Parse Pest test output and cluster failures by common file and exception."""
    root = repo_root or Path.cwd()
    clean_raw = strip_root_from_text(raw_output, root)

    failures: list[dict[str, Any]] = []
    seen_tests: set[str] = set()

    # Split specifically on the detailed FAILED test blocks
    fail_blocks = re.split(r"\n\s*FAILED\s+", clean_raw)

    for block in fail_blocks[1:]:
        lines = [line.strip() for line in block.splitlines() if line.strip()]
        if not lines:
            continue

        test_name = lines[0]
        # Clean test name from trailing ellipsis or noise
        test_name = re.sub(r"[…\.]+$", "", test_name).strip()

        if test_name in seen_tests:
            continue
        seen_tests.add(test_name)

        error_msg = ""
        file_loc = ""
        line_num = 0

        for line in lines:
            if "at tests" in line or "at app" in line or line.startswith("at "):
                raw_loc = line.replace("at ", "").strip()
                if ":" in raw_loc:
                    parts = raw_loc.rsplit(":", 1)
                    file_loc = to_relative_posix(parts[0], root)
                    line_num = int(parts[1]) if parts[1].isdigit() else 0
                else:
                    file_loc = to_relative_posix(raw_loc, root)
            elif (
                any(kw in line for kw in ("Failed asserting", "Expected", "Exception", "Error"))
                and not error_msg
            ):
                error_msg = line

        failures.append(
            {
                "test": test_name,
                "error": error_msg or "Assertion or runtime error",
                "file": file_loc,
                "line": line_num,
            }
        )

    # Collapse failures by parent directory (e.g. tests/Unit, tests/Feature/Settings)
    collapsed_dirs = collapse_by_parent_dir(failures, file_key="file")

    # Extract summary line (Tests: N failed, M passed)
    summary_match = re.search(r"Tests:\s+([^\n\r]+)", clean_raw)
    summary_str = summary_match.group(0) if summary_match else "Tests summary unavailable"

    return {
        "failure_count": len(failures),
        "summary": summary_str,
        "failures_by_directory": collapsed_dirs,
    }


def triage_tests(repo_root: Path | None = None) -> dict[str, Any]:
    """Execute Pest and generate batch triage report."""
    root = find_repo_root(repo_root)
    pest_bin = root / "vendor" / "bin" / "pest"

    if not pest_bin.is_file():
        return {"error": "vendor/bin/pest not found", "exit_code": 1}

    pest_res = run_command(["php", str(pest_bin), "--ci"], cwd=root, use_snip=False, timeout_seconds=180)
    raw = (pest_res.stdout or "") + "\n" + (pest_res.stderr or "")

    parsed = parse_pest_output(raw, repo_root=root)
    parsed["exit_code"] = pest_res.exit_code
    parsed["duration_ms"] = pest_res.duration_ms

    return parsed
