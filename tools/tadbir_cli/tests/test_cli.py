from __future__ import annotations

from unittest.mock import patch

from tadbir.cli import create_parser, main


def test_create_parser() -> None:
    parser = create_parser()
    args = parser.parse_args(["status", "--cached"])
    assert args.subcommand == "status"
    assert args.cached is True

    args_pr = parser.parse_args(["pr", "34"])
    assert args_pr.subcommand == "pr"
    assert args_pr.number == 34


def test_main_status() -> None:
    mock_status = {
        "verified_at": "2026-09-01T00:00:00Z",
        "git": {"branch": "main"},
    }
    with patch("tadbir.cli.get_status", return_value=mock_status):
        exit_code = main(["status"])
        assert exit_code == 0


def test_main_gate_exit_codes() -> None:
    with patch("tadbir.cli.run_gate", return_value={"exit_code": 0}):
        assert main(["gate"]) == 0

    with patch("tadbir.cli.run_gate", return_value={"exit_code": 1}):
        assert main(["gate"]) == 1
