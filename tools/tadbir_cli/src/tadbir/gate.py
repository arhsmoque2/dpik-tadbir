"""Local atomic quality gate harness emitting raw verified state without verdicts."""

from __future__ import annotations

import json
import re
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from tadbir.path_utils import strip_root_from_text
from tadbir.snip_runner import run_command
from tadbir.status import find_repo_root


def parse_pest_metrics(raw_text: str) -> dict[str, Any]:
    """Extract raw test numbers (passed, failed, skipped, assertions) from Pest output."""
    passed = 0
    failed = 0
    skipped = 0
    assertions = 0

    # Match summary line: e.g. "Tests: 2 failed, 167 passed (823 assertions)"
    # or "Tests: 187 passed (860 assertions)"
    match = re.search(r"Tests:\s+([^\n\r]+)", raw_text)
    summary_str = match.group(1).strip() if match else ""

    if summary_str:
        p_match = re.search(r"(\d+)\s+passed", summary_str)
        if p_match:
            passed = int(p_match.group(1))

        f_match = re.search(r"(\d+)\s+failed", summary_str)
        if f_match:
            failed = int(f_match.group(1))

        s_match = re.search(r"(\d+)\s+skipped", summary_str)
        if s_match:
            skipped = int(s_match.group(1))

        a_match = re.search(r"\((\d+)\s+assertions\)", summary_str)
        if a_match:
            assertions = int(a_match.group(1))

    return {
        "summary": summary_str or "unparsed",
        "total_tests": passed + failed + skipped,
        "passed": passed,
        "failed": failed,
        "skipped": skipped,
        "assertions": assertions,
    }


def parse_phpstan_metrics(raw_text: str) -> dict[str, Any]:
    """Extract error count and issue descriptions from PHPStan output."""
    errors: list[str] = []
    error_count = 0

    # Look for "Found X errors" or "[ERROR]"
    count_match = re.search(r"Found\s+(\d+)\s+errors?", raw_text, re.IGNORECASE)
    if count_match:
        error_count = int(count_match.group(1))
    elif "[OK] No errors" in raw_text or "no errors found" in raw_text.lower():
        error_count = 0
    elif "error" in raw_text.lower():
        # Count error indicator lines
        error_lines = [line.strip() for line in raw_text.splitlines() if "Line " in line or "ERROR" in line]
        error_count = len(error_lines) or 1

    # Extract clean error lines
    for line in raw_text.splitlines():
        trimmed = line.strip()
        if trimmed and any(kw in trimmed for kw in ("Line ", "ERROR", "crashed", "memory limit", "exception")):
            errors.append(trimmed)

    return {
        "error_count": error_count,
        "errors": errors[:15],
    }


def parse_pint_metrics(raw_text: str) -> dict[str, Any]:
    """Extract style violations count and file list from Pint output."""
    violations: list[str] = []
    violations_count = 0

    try:
        parsed = json.loads(raw_text)
        if isinstance(parsed, dict):
            if parsed.get("result") == "passed":
                return {"violations_count": 0, "violations": []}
            elif "files" in parsed and isinstance(parsed["files"], list):
                violations = [f.get("name", "") for f in parsed["files"]]
                return {"violations_count": len(violations), "violations": violations}
    except Exception:
        pass

    # Human format fallback
    for line in raw_text.splitlines():
        trimmed = line.strip()
        if "FAIL" in trimmed or "⨯" in trimmed or trimmed.endswith(".php"):
            violations.append(trimmed)

    violations_count = len(violations)
    return {
        "violations_count": violations_count,
        "violations": violations[:15],
    }


def _phpstan_level(root: Path) -> int:
    """Read the analysis level from phpstan.neon (0 if absent or unreadable)."""
    try:
        text = (root / "phpstan.neon").read_text(encoding="utf-8", errors="replace")
    except OSError:
        return 0
    for line in text.splitlines():
        m = re.match(r"\s*level:\s*(\d+)", line)
        if m:
            return int(m.group(1))
    return 0


def run_gate(repo_root: Path | None = None, use_snip: bool = True) -> dict[str, Any]:
    """Execute Pint, PHPStan and Pest and emit raw verified state without verdicts.

    Tool output is parsed into metrics here, so snip filtering is not used (the
    JSON payload is the compression). `use_snip` is accepted for back-compat.
    """
    root = find_repo_root(repo_root)
    start_time = time.perf_counter()
    _ = use_snip  # retained for CLI back-compat; gate parses output directly

    results: dict[str, Any] = {}
    all_exit_zero = True

    # 1. Pint Gate
    pint_bin = root / "vendor" / "bin" / "pint"
    if pint_bin.is_file():
        pint_res = run_command(["php", str(pint_bin), "--test"], cwd=root, use_snip=False)
        if pint_res.exit_code != 0:
            all_exit_zero = False
        raw_out = pint_res.stdout or pint_res.stderr or ""
        pint_metrics = parse_pint_metrics(raw_out)
        results["pint"] = {
            "command": "vendor/bin/pint --test",
            "exit_code": pint_res.exit_code,
            "duration_ms": pint_res.duration_ms,
            "violations_count": pint_metrics["violations_count"],
            "violations": pint_metrics["violations"],
            "raw_output": strip_root_from_text(raw_out, root) if pint_res.exit_code != 0 else "",
        }
    else:
        results["pint"] = {
            "command": "vendor/bin/pint --test",
            "exit_code": 1,
            "duration_ms": 0,
            "violations_count": 1,
            "violations": ["vendor/bin/pint not found"],
            "raw_output": "Binary missing",
        }
        all_exit_zero = False

    # 2. PHPStan Gate — no --level override: phpstan.neon governs (level 8),
    # matching CI and `composer check:quick`. 1G memory ceiling matches CI.
    stan_bin = root / "vendor" / "bin" / "phpstan"
    if stan_bin.is_file():
        stan_res = run_command(
            ["php", str(stan_bin), "analyse", "--no-progress", "--memory-limit=1G"],
            cwd=root,
            use_snip=False,
        )
        if stan_res.exit_code != 0:
            all_exit_zero = False
        raw_stan = stan_res.stdout or stan_res.stderr or ""
        stan_metrics = parse_phpstan_metrics(raw_stan)
        results["phpstan"] = {
            "command": "vendor/bin/phpstan analyse --memory-limit=1G",
            "exit_code": stan_res.exit_code,
            "duration_ms": stan_res.duration_ms,
            "level": _phpstan_level(root),
            "error_count": stan_metrics["error_count"],
            "errors": stan_metrics["errors"],
            "raw_output": strip_root_from_text(raw_stan, root) if stan_res.exit_code != 0 else "",
        }
    else:
        results["phpstan"] = {
            "command": "vendor/bin/phpstan analyse --memory-limit=1G",
            "exit_code": 1,
            "duration_ms": 0,
            "level": _phpstan_level(root),
            "error_count": 1,
            "errors": ["vendor/bin/phpstan not found"],
            "raw_output": "Binary missing",
        }
        all_exit_zero = False

    # 3. Pest Gate
    pest_bin = root / "vendor" / "bin" / "pest"
    if pest_bin.is_file():
        pest_res = run_command(["php", str(pest_bin), "--ci"], cwd=root, use_snip=False, timeout_seconds=180)
        if pest_res.exit_code != 0:
            all_exit_zero = False
        raw_pest = (pest_res.stdout or "") + "\n" + (pest_res.stderr or "")
        pest_metrics = parse_pest_metrics(raw_pest)
        results["pest"] = {
            "command": "vendor/bin/pest --ci",
            "exit_code": pest_res.exit_code,
            "duration_ms": pest_res.duration_ms,
            "summary": pest_metrics["summary"],
            "total_tests": pest_metrics["total_tests"],
            "passed": pest_metrics["passed"],
            "failed": pest_metrics["failed"],
            "skipped": pest_metrics["skipped"],
            "assertions": pest_metrics["assertions"],
            "raw_output": strip_root_from_text(raw_pest, root) if pest_res.exit_code != 0 else "",
        }
    else:
        results["pest"] = {
            "command": "vendor/bin/pest --ci",
            "exit_code": 1,
            "duration_ms": 0,
            "summary": "Binary missing",
            "total_tests": 0,
            "passed": 0,
            "failed": 1,
            "skipped": 0,
            "assertions": 0,
            "raw_output": "vendor/bin/pest not found",
        }
        all_exit_zero = False

    total_duration_sec = round(time.perf_counter() - start_time, 2)

    gate_payload = {
        "verified_at": datetime.now(timezone.utc).isoformat(),
        "duration_seconds": total_duration_sec,
        "exit_code": 0 if all_exit_zero else 1,
        "scope": "fast subset (Pint, PHPStan, Pest)",
        "authoritative_gate": "composer check:full (adds FilaCheck, composer-unused, diff-cover >= 90)",
        "gates": results,
    }

    # Cache result to .tadbir/last-gate-result.json
    cache_dir = root / ".tadbir"
    try:
        cache_dir.mkdir(parents=True, exist_ok=True)
        (cache_dir / "last-gate-result.json").write_text(json.dumps(gate_payload, indent=2), encoding="utf-8")
    except Exception:
        pass

    return gate_payload
