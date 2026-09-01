from __future__ import annotations

import sys
from pathlib import Path

from tadbir.snip_runner import find_snip_binary, run_command, strip_ansi


def test_strip_ansi() -> None:
    colored_text = "\x1b[31mError:\x1b[0m Failed test \x1b[1mat line 42\x1b[0m"
    clean = strip_ansi(colored_text)
    assert clean == "Error: Failed test at line 42"


def test_find_snip_binary() -> None:
    snip_bin = find_snip_binary()
    if snip_bin:
        assert Path(snip_bin).is_file()


def test_run_command_success() -> None:
    res = run_command([sys.executable, "-c", "print('hello tadbir')"], use_snip=False)
    assert res.exit_code == 0
    assert res.stdout == "hello tadbir"
    assert res.duration_ms >= 0


def test_run_command_failure() -> None:
    res = run_command([sys.executable, "-c", "import sys; sys.exit(42)"], use_snip=False)
    assert res.exit_code == 42


def test_run_command_timeout() -> None:
    res = run_command([sys.executable, "-c", "import time; time.sleep(2)"], timeout_seconds=1, use_snip=False)
    assert res.exit_code == 124
    assert "timed out" in res.stderr
