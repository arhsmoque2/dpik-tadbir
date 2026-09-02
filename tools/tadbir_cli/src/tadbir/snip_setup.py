"""snip filter registration and verification for the tadbir control plane.

The current snip release loads project-local filter directories only when they
are listed in the *global* config (``~/.config/snip/config.toml``) ``filters.dir``
array and trusted via ``snip trust``. ``setup_snip`` performs both steps
idempotently; ``verify_snip`` runs the inline filter tests. Both emit raw state
(no verdict strings) per the State > Verdict doctrine.
"""

from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Any

from tadbir.snip_runner import find_snip_binary, run_command
from tadbir.status import find_repo_root

PROJECT_FILTERS = ["pest", "phpstan", "pint", "gh-run", "gh-run-log-failed", "artisan-migrate"]


def _global_config_path() -> Path:
    override = os.environ.get("SNIP_CONFIG")
    if override:
        return Path(override)
    return Path.home() / ".config" / "snip" / "config.toml"


def _filters_dir(repo_root: Path) -> Path:
    return repo_root / ".snip" / "filters"


def _ensure_dir_registered(config_path: Path, target: str) -> str:
    """Ensure `target` is in the global config filters.dir array. Returns the
    action taken: 'already-present' | 'array-extended' | 'string-promoted' |
    'block-created'."""
    text = config_path.read_text(encoding="utf-8") if config_path.is_file() else ""

    if target in text:
        return "already-present"

    if "[filters]" not in text:
        block = f'\n[filters]\ndir = ["~/.config/snip/filters", "{target}"]\n'
        config_path.parent.mkdir(parents=True, exist_ok=True)
        config_path.write_text((text.rstrip() + "\n" + block) if text else block.lstrip(), encoding="utf-8")
        return "block-created"

    array_match = re.search(r'(dir\s*=\s*\[)([^\]]*)(\])', text)
    if array_match:
        new_text = text[: array_match.start()] + array_match.group(1) + array_match.group(2).rstrip() + (
            "" if array_match.group(2).strip().endswith(",") or not array_match.group(2).strip() else ", "
        ) + f'"{target}"' + array_match.group(3) + text[array_match.end():]
        config_path.write_text(new_text, encoding="utf-8")
        return "array-extended"

    string_match = re.search(r'dir\s*=\s*"([^"]*)"', text)
    if string_match:
        existing = string_match.group(1)
        new_text = text[: string_match.start()] + f'dir = ["{existing}", "{target}"]' + text[string_match.end():]
        config_path.write_text(new_text, encoding="utf-8")
        return "string-promoted"

    # [filters] exists but no dir key — append one under it.
    new_text = text.replace("[filters]", f'[filters]\ndir = ["~/.config/snip/filters", "{target}"]', 1)
    config_path.write_text(new_text, encoding="utf-8")
    return "block-created"


def _parse_verify(output: str) -> dict[str, Any]:
    """Parse `snip verify` stdout into per-filter pass/fail and a totals line."""
    per_filter: dict[str, str] = {}
    for line in output.splitlines():
        m = re.match(r"^([a-z0-9-]+)\s+\.+\s+(\d+)/(\d+)\s+passed", line.strip())
        if m and m.group(1) in PROJECT_FILTERS:
            per_filter[m.group(1)] = f"{m.group(2)}/{m.group(3)}"
    totals = ""
    tm = re.search(r"(\d+ filters?, .*passed, \d+ failed)", output)
    if tm:
        totals = tm.group(1)
    invalid = [ln.strip() for ln in output.splitlines() if "skipping invalid filter" in ln]
    return {"per_filter": per_filter, "totals": totals, "invalid": invalid}


def setup_snip(repo_root: Path | None = None) -> dict[str, Any]:
    root = find_repo_root(repo_root)
    filters_dir = _filters_dir(root)
    target = filters_dir.as_posix()
    snip_bin = find_snip_binary()

    payload: dict[str, Any] = {
        "snip_binary": snip_bin,
        "snip_available": snip_bin is not None,
        "filters_dir": target,
        "filters_present": sorted(p.stem for p in filters_dir.glob("*.yaml")),
        "global_config": str(_global_config_path()),
    }

    if not filters_dir.is_dir():
        payload["error"] = f"{target} does not exist"
        payload["exit_code"] = 1
        return payload

    if snip_bin is None:
        payload["config_action"] = "skipped"
        payload["note"] = "snip not installed; filters are versioned in the repo and will activate once snip is present"
        payload["exit_code"] = 0
        return payload

    payload["config_action"] = _ensure_dir_registered(_global_config_path(), target)

    trust_res = run_command([snip_bin, "trust", target], cwd=root, use_snip=False)
    payload["trust_exit_code"] = trust_res.exit_code
    payload["trusted"] = [
        ln.split(" (")[0].removeprefix("trusted: ")
        for ln in trust_res.stdout.splitlines()
        if ln.startswith("trusted:")
    ]

    verify_res = run_command([snip_bin, "verify"], cwd=root, use_snip=False)
    payload["verify"] = _parse_verify(verify_res.stdout + "\n" + verify_res.stderr)
    payload["exit_code"] = 0 if _verify_ok(payload["verify"]) and trust_res.exit_code == 0 else 1
    return payload


def _verify_ok(verify: dict[str, Any]) -> bool:
    """True when no project filter is invalid and each one that ran passed all
    its inline tests."""
    if verify["invalid"]:
        return False
    for name, ratio in verify["per_filter"].items():
        if name in PROJECT_FILTERS:
            passed, _, total = ratio.partition("/")
            if passed != total or total == "0":
                return False
    return True


def verify_snip(repo_root: Path | None = None) -> dict[str, Any]:
    root = find_repo_root(repo_root)
    snip_bin = find_snip_binary()
    filters_dir = _filters_dir(root)

    payload: dict[str, Any] = {
        "snip_available": snip_bin is not None,
        "filters_present": sorted(p.stem for p in filters_dir.glob("*.yaml")),
    }
    if snip_bin is None:
        payload["note"] = "snip not installed; run 'python tools/tadbir.py snip-setup' after installing"
        payload["exit_code"] = 0
        return payload

    res = run_command([snip_bin, "verify"], cwd=root, use_snip=False)
    payload["verify"] = _parse_verify(res.stdout + "\n" + res.stderr)
    missing = [f for f in PROJECT_FILTERS if f not in payload["verify"]["per_filter"]]
    payload["unregistered_filters"] = missing
    payload["exit_code"] = 0 if (not payload["verify"]["invalid"] and not missing) else 1
    return payload
