from __future__ import annotations

from pathlib import Path

from tadbir.path_utils import collapse_by_parent_dir, normalize_posix, strip_root_from_text, to_relative_posix


def test_normalize_posix() -> None:
    assert normalize_posix("app\\Services\\AuthService.php") == "app/Services/AuthService.php"


def test_to_relative_posix() -> None:
    root = "D:/ARH-GITHUB/arhsmoque2/dpik-tadbir"
    path = "D:\\ARH-GITHUB\\arhsmoque2\\dpik-tadbir\\app\\Services\\AuthService.php"
    assert to_relative_posix(path, root) == "app/Services/AuthService.php"


def test_strip_root_from_text() -> None:
    root = Path("D:/ARH-GITHUB/arhsmoque2/dpik-tadbir")
    text = "Error in D:\\ARH-GITHUB\\arhsmoque2\\dpik-tadbir\\app\\Models\\User.php at line 10"
    cleaned = strip_root_from_text(text, root)
    assert cleaned == "Error in app/Models/User.php at line 10"


def test_collapse_by_parent_dir() -> None:
    items = [
        {"file": "tests/Unit/MailDiagnosticServiceTest.php", "line": 35},
        {"file": "tests/Unit/AuthServiceTest.php", "line": 42},
        {"file": "tests/Feature/Settings/ImapConfigurationTest.php", "line": 62},
    ]
    collapsed = collapse_by_parent_dir(items, file_key="file")
    assert "tests/Unit" in collapsed
    assert "tests/Feature/Settings" in collapsed
    assert len(collapsed["tests/Unit"]) == 2
    assert collapsed["tests/Unit"][0]["file"] == "MailDiagnosticServiceTest.php"
    assert collapsed["tests/Feature/Settings"][0]["file"] == "ImapConfigurationTest.php"
