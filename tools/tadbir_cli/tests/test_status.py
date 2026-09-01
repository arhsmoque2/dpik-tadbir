from __future__ import annotations

from tadbir.status import find_repo_root, get_status


def test_find_repo_root() -> None:
    root = find_repo_root()
    assert (root / "composer.json").is_file()


def test_get_status_structure() -> None:
    status = get_status()
    assert "verified_at" in status
    assert "probe_duration_ms" in status
    assert "git" in status
    assert "branch" in status["git"]
    assert "toolchain" in status
    assert "manifest_validation" in status
    assert "continuity" in status
    assert "handoff_file" in status["continuity"]
