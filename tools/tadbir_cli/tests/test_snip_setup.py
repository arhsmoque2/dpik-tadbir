from __future__ import annotations

from pathlib import Path

from tadbir.snip_setup import _ensure_dir_registered, _parse_verify, _verify_ok


def test_ensure_dir_registered_creates_block(tmp_path: Path) -> None:
    cfg = tmp_path / "config.toml"
    action = _ensure_dir_registered(cfg, "/repo/.snip/filters")
    assert action == "block-created"
    assert '"/repo/.snip/filters"' in cfg.read_text()


def test_ensure_dir_registered_extends_array(tmp_path: Path) -> None:
    cfg = tmp_path / "config.toml"
    cfg.write_text('[filters]\ndir = ["~/.config/snip/filters"]\n')
    action = _ensure_dir_registered(cfg, "/repo/.snip/filters")
    assert action == "array-extended"
    text = cfg.read_text()
    assert '"~/.config/snip/filters"' in text and '"/repo/.snip/filters"' in text


def test_ensure_dir_registered_promotes_string(tmp_path: Path) -> None:
    cfg = tmp_path / "config.toml"
    cfg.write_text('[filters]\ndir = "~/.config/snip/filters"\n')
    action = _ensure_dir_registered(cfg, "/repo/.snip/filters")
    assert action == "string-promoted"
    assert 'dir = ["~/.config/snip/filters", "/repo/.snip/filters"]' in cfg.read_text()


def test_ensure_dir_registered_idempotent(tmp_path: Path) -> None:
    cfg = tmp_path / "config.toml"
    _ensure_dir_registered(cfg, "/repo/.snip/filters")
    action = _ensure_dir_registered(cfg, "/repo/.snip/filters")
    assert action == "already-present"


def test_parse_verify_and_ok() -> None:
    out = (
        "pest ................ 2/2 passed\n"
        "phpstan ............. 3/3 passed\n"
        "132 filters, 18 tested, 45 tests, 45 passed, 0 failed\n"
    )
    parsed = _parse_verify(out)
    assert parsed["per_filter"]["pest"] == "2/2"
    assert "0 failed" in parsed["totals"]
    assert _verify_ok(parsed) is True


def test_verify_ok_fails_on_invalid() -> None:
    parsed = {"per_filter": {}, "totals": "", "invalid": ["snip: skipping invalid filter pest.yaml"]}
    assert _verify_ok(parsed) is False
