"""Subprocess execution harness with snip.exe integration and clean fallback."""

from __future__ import annotations

import os
import re
import shutil
import subprocess
import time
from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class CommandResult:
    command: list[str]
    exit_code: int
    duration_ms: int
    stdout: str
    stderr: str
    snipped: bool


ANSI_REGEX = re.compile(r"\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])")


def strip_ansi(text: str) -> str:
    """Remove ANSI escape codes from string."""
    return ANSI_REGEX.sub("", text)


def find_snip_binary() -> str | None:
    """Locate the snip executable.

    Resolution order: ``SNIP_BIN`` env override, then ``PATH``, then the
    conventional per-user install locations (``~/.local/bin``, ``~/go/bin``).
    No host-specific absolute paths — this runs on Windows hosts, Linux
    containers and CI alike.
    """
    override = os.environ.get("SNIP_BIN")
    if override and Path(override).is_file():
        return override

    snip_path = shutil.which("snip") or shutil.which("snip.exe")
    if snip_path:
        return snip_path

    home = Path.home()
    for rel in ("snip", "snip.exe"):
        for base in (home / ".local" / "bin", home / "go" / "bin"):
            candidate = base / rel
            if candidate.is_file() and os.access(candidate, os.X_OK):
                return str(candidate)

    return None


def run_command(
    cmd: list[str],
    cwd: Path | str | None = None,
    use_snip: bool = True,
    timeout_seconds: int = 120,
    env: dict[str, str] | None = None,
) -> CommandResult:
    """Execute command with optional snip filtering and timing telemetry."""
    cwd_path = Path(cwd) if cwd else Path.cwd()
    snip_bin = find_snip_binary() if use_snip else None

    actual_cmd: list[str] = []
    is_snipped = False

    if snip_bin:
        actual_cmd = [snip_bin, "run", "--"] + cmd
        is_snipped = True
    else:
        actual_cmd = cmd

    exec_env = os.environ.copy()
    if env:
        exec_env.update(env)

    start_time = time.perf_counter()
    try:
        proc = subprocess.run(
            actual_cmd,
            cwd=str(cwd_path),
            capture_output=True,
            text=True,
            timeout=timeout_seconds,
            env=exec_env,
            encoding="utf-8",
            errors="replace",
        )
        # snip on Windows spawns the child through cmd.exe, which cannot exec a
        # shebang script (vendor/bin/pest et al). Detect that specific failure
        # and retry the command raw so a missing snip integration never breaks a
        # probe — the raw output is still parsed downstream.
        if (
            is_snipped
            and proc.returncode != 0
            and "is not recognized as an internal or external command" in (proc.stderr or "")
        ):
            proc = subprocess.run(
                cmd,
                cwd=str(cwd_path),
                capture_output=True,
                text=True,
                timeout=timeout_seconds,
                env=exec_env,
                encoding="utf-8",
                errors="replace",
            )
            is_snipped = False
        duration_ms = int((time.perf_counter() - start_time) * 1000)
        stdout = strip_ansi(proc.stdout or "").strip()
        stderr = strip_ansi(proc.stderr or "").strip()
        exit_code = proc.returncode
    except subprocess.TimeoutExpired:
        duration_ms = int((time.perf_counter() - start_time) * 1000)
        stdout = ""
        stderr = f"Command timed out after {timeout_seconds}s: {' '.join(cmd)}"
        exit_code = 124
    except Exception as exc:
        duration_ms = int((time.perf_counter() - start_time) * 1000)
        stdout = ""
        stderr = f"Failed to execute command: {exc}"
        exit_code = 1

    return CommandResult(
        command=cmd,
        exit_code=exit_code,
        duration_ms=duration_ms,
        stdout=stdout,
        stderr=stderr,
        snipped=is_snipped,
    )
