"""Structural validation of the repo's snip filters.

These run without the snip binary (CI has no snip), so they check YAML shape,
required keys, known pipeline actions and inline-test presence. Full behavioural
verification is `snip verify` via `python tools/tadbir.py snip-verify`.
"""

from __future__ import annotations

import shutil
import subprocess
from pathlib import Path

import pytest
import yaml

from tadbir.snip_setup import PROJECT_FILTERS, _parse_verify, _verify_ok

FILTERS_DIR = Path(__file__).resolve().parents[3] / ".snip" / "filters"

# The 20 built-in snip pipeline actions (SKILL.md).
KNOWN_ACTIONS = {
    "keep_lines", "remove_lines", "head", "tail", "dedup",
    "truncate_lines", "replace", "truncate_bytes", "strip_ansi", "compact_path",
    "regex_extract", "group_by", "aggregate", "state_machine",
    "json_extract", "json_schema", "ndjson_stream",
    "format_template", "match_output", "on_empty",
}


def _filter_files() -> list[Path]:
    return sorted(FILTERS_DIR.glob("*.yaml"))


def test_filters_dir_exists() -> None:
    assert FILTERS_DIR.is_dir(), f"{FILTERS_DIR} missing"


def test_all_expected_filters_present() -> None:
    present = {p.stem for p in _filter_files()}
    assert set(PROJECT_FILTERS) <= present, f"missing: {set(PROJECT_FILTERS) - present}"


@pytest.mark.parametrize("path", _filter_files(), ids=lambda p: p.stem)
def test_filter_is_well_formed(path: Path) -> None:
    doc = yaml.safe_load(path.read_text(encoding="utf-8"))
    assert isinstance(doc, dict), "filter must be a mapping"
    assert doc.get("name"), "name is required"
    assert doc.get("match", {}).get("command"), "match.command is required"

    pipeline = doc.get("pipeline")
    assert isinstance(pipeline, list) and pipeline, "pipeline must be a non-empty list"
    for step in pipeline:
        assert step["action"] in KNOWN_ACTIONS, f"unknown action {step['action']!r}"

    tests = doc.get("tests")
    assert isinstance(tests, list) and tests, "every filter must carry inline tests"
    for tc in tests:
        assert "name" in tc and "input" in tc and "expected" in tc


def test_snip_verify_passes_when_binary_available() -> None:
    """When snip is installed, its inline tests for our filters must pass."""
    snip = shutil.which("snip") or shutil.which("snip.exe")
    if not snip:
        pytest.skip("snip binary not on PATH")
    repo_root = FILTERS_DIR.parents[1]
    proc = subprocess.run(
        [snip, "verify"], cwd=repo_root, capture_output=True, text=True, timeout=60
    )
    parsed = _parse_verify(proc.stdout + "\n" + proc.stderr)
    assert not parsed["invalid"], parsed["invalid"]
    missing = [n for n in PROJECT_FILTERS if n not in parsed["per_filter"]]
    assert not missing, f"not reported by snip verify (dir not registered?): {missing}"
    assert _verify_ok(parsed), parsed["per_filter"]
