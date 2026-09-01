"""Path normalization and directory-prefix compression utilities for token efficiency."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any


def normalize_posix(path_str: str) -> str:
    """Normalize Windows backslashes to POSIX forward slashes."""
    return path_str.replace("\\", "/").strip()


def _safe_resolve(root: Path | str) -> str:
    """Resolved form of `root`, or "" if it cannot be resolved sanely on this
    platform (e.g. a "D:/…" path on Linux resolves against the cwd)."""
    try:
        resolved = str(Path(root).resolve())
    except (OSError, ValueError):
        return ""
    return resolved if resolved != str(root) else ""


def to_relative_posix(path_or_str: Path | str, root: Path | str) -> str:
    """Convert an absolute or dirty path to clean forward-slash relative path."""
    raw_str = normalize_posix(str(path_or_str))
    root_str = normalize_posix(str(root)).rstrip("/") + "/"

    if raw_str.startswith(root_str):
        raw_str = raw_str[len(root_str):]
    elif raw_str.startswith("phar://" + root_str):
        raw_str = raw_str[len("phar://" + root_str):]

    return raw_str.lstrip("./")


def strip_root_from_text(text: str, root: Path | str) -> str:
    """Strip repeated absolute repo root path prefixes from logs/traces.

    Strips the root in every form it may appear: the raw string as given, and —
    when it resolves to something different on this platform — the resolved
    form too. Never calls ``.resolve()`` as the *only* basis: a Windows-shaped
    root ("D:/…") resolved on Linux would be mis-anchored to the cwd.
    """
    if not text:
        return ""
    forms: set[str] = set()
    for candidate in (str(root), _safe_resolve(root)):
        if not candidate:
            continue
        posix = normalize_posix(candidate)
        forms.add(posix)
        forms.add(posix.replace("/", "\\"))  # Windows-separator form of the same root
    # Longest first so a resolved (longer) prefix strips before a bare one.
    for form in sorted(forms, key=len, reverse=True):
        text = text.replace(form + "\\", "")
        text = text.replace(form + "/", "")
        text = text.replace(form, "")
    # Normalize remaining backslashes in paths like tests\Unit\...
    text = re.sub(
        r"\b(app|tests|vendor|config|database|routes)\\[a-zA-Z0-9_\\\.-]+",
        lambda m: normalize_posix(m.group(0)),
        text,
    )
    return text.strip()


def collapse_by_parent_dir(items: list[dict[str, Any]], file_key: str = "file") -> dict[str, list[dict[str, Any]]]:
    """Group file items by parent directory to eliminate repeating path prefixes."""
    collapsed: dict[str, list[dict[str, Any]]] = {}

    for item in items:
        full_file = normalize_posix(str(item.get(file_key, "")))
        if "/" in full_file:
            parent_dir, filename = full_file.rsplit("/", 1)
        else:
            parent_dir = "."
            filename = full_file

        item_copy = dict(item)
        item_copy[file_key] = filename

        if parent_dir not in collapsed:
            collapsed[parent_dir] = []
        collapsed[parent_dir].append(item_copy)

    return collapsed
