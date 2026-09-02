from __future__ import annotations

import pytest

from tadbir.cli import _ci_exit_code


@pytest.mark.parametrize(
    ("conclusion", "status", "expected"),
    [
        ("success", "completed", 0),
        ("neutral", "completed", 0),
        ("skipped", "completed", 0),
        ("failure", "completed", 1),
        ("cancelled", "completed", 1),
        ("timed_out", "completed", 1),
        ("startup_failure", "completed", 1),
        # still running: a PR whose CI has not finished is NOT a failure
        ("", "in_progress", 0),
        ("", "queued", 0),
        (None, "in_progress", 0),
        ("in_progress", "in_progress", 0),  # pr.py stuffs this into conclusion
        ("unknown", "", 0),
        # error sentinels from check_ci
        ("", "error", 1),
    ],
)
def test_ci_exit_code(conclusion: str, status: str, expected: int) -> None:
    assert _ci_exit_code(conclusion or "", status) == expected
