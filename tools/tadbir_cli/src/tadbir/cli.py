"""CLI entry point for tadbir command plane."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any

from tadbir.ci import check_ci
from tadbir.gate import run_gate
from tadbir.pr import triage_pr
from tadbir.snip_setup import setup_snip, verify_snip
from tadbir.status import get_status
from tadbir.triage import triage_tests

HELP_DATA: dict[str, Any] = {
    "control_plane": "tadbir",
    "description": "DPIK Tadbir deterministic runtime control plane and quality harness",
    "decision_matrix": [
        {
            "trigger": "Session cold-start, repo switch, or baseline recon",
            "command": "python tools/tadbir.py status",
            "purpose": "1-turn live state (git, CI, toolchain, manifest check, handoff)",
            "flags": ["--cached (read cached local gate state)"],
        },
        {
            "trigger": "Before git push OR after modifying PHP/tests",
            "command": "python tools/tadbir.py gate",
            "purpose": "Atomic local gates (Pint, PHPStan L5 512M, Pest) through snip",
            "rule": "MANDATORY. Never git push if gate returns non-zero",
        },
        {
            "trigger": "Assigned to investigate or fix a PR (e.g. PR #34)",
            "command": "python tools/tadbir.py pr [PR_NUMBER]",
            "purpose": "1-turn PR state, failing CI gate/step, and exact missing coverage lines",
        },
        {
            "trigger": "Check remote GitHub Actions CI run without polling loop",
            "command": "python tools/tadbir.py ci-wait [--branch BRANCH]",
            "purpose": "Single-shot status check (completed/in_progress). Do not poll in a loop",
        },
        {
            "trigger": "Multiple Pest test failures in complex suites",
            "command": "python tools/tadbir.py test-triage",
            "purpose": "Batch clusters failures by common file and exception root cause",
        },
        {
            "trigger": "First checkout on a machine, or after editing .snip/filters/*.yaml",
            "command": "python tools/tadbir.py snip-setup",
            "purpose": "Register + trust this repo's snip output filters in the global snip config; runs snip verify",
        },
    ],
    "agent_invariants": [
        "Playwright E2E is non-blocking in CI (continue-on-error). Do not block on Playwright timeout.",
        "All commands emit valid JSON to stdout. exit 0 = pass/healthy/pending, 1 = fail/error.",
        "gate/test-triage parse tool output into metrics directly. Raw pest/phpstan/pint/gh-run output "
        "is compressed by .snip/filters/*.yaml when run through snip's PreToolUse hook.",
        "gate runs PHPStan at the level in phpstan.neon (8). It is the fast subset; composer check:full "
        "(FilaCheck, composer-unused, diff-cover) remains the authoritative pre-merge gate.",
    ],
}


_PENDING_STATUSES = {"in_progress", "queued", "waiting", "pending", "requested"}


def _ci_exit_code(conclusion: str, status: str = "") -> int:
    """Map a GitHub Actions run to an exit code.

    0 = passing OR still running (a PR whose CI has not finished is not a
    failure). 1 = a real failing/errored conclusion or an unparseable state.
    """
    conclusion = (conclusion or "").lower()
    status = (status or "").lower()
    if conclusion in ("success", "neutral", "skipped"):
        return 0
    if conclusion in ("failure", "cancelled", "timed_out", "startup_failure", "action_required", "stale"):
        return 1
    if conclusion in _PENDING_STATUSES or conclusion in ("", "unknown", "none"):
        if status in _PENDING_STATUSES or status in ("", "in_progress"):
            return 0
    return 1


class AgentHelpParser(argparse.ArgumentParser):
    """Custom ArgumentParser that emits token-efficient JSON help for AI agents."""

    def print_help(self, file: Any = None) -> None:
        if file is None:
            file = sys.stdout
        file.write(json.dumps(HELP_DATA, indent=2) + "\n")

    def format_help(self) -> str:
        return json.dumps(HELP_DATA, indent=2) + "\n"


def create_parser() -> argparse.ArgumentParser:
    parser = AgentHelpParser(
        prog="tadbir",
        description="DPIK Tadbir runtime control plane and quality gate harness",
        add_help=True,
    )
    parser.add_argument(
        "--root",
        type=Path,
        default=None,
        help="Target repository root (default: auto-detected)",
    )
    # Output is always structured JSON; the flag is accepted for callers that
    # pass it explicitly but there is no non-JSON mode.
    parser.add_argument(
        "--json",
        action="store_true",
        help="No-op: tadbir always emits JSON to stdout",
    )

    subparsers = parser.add_subparsers(dest="subcommand", required=False)

    # help command
    subparsers.add_parser("help", help="Show token-efficient agent help and decision matrix")

    # status
    status_parser = subparsers.add_parser("status", help="Get verified live status and validate manifest")
    status_parser.add_argument("--cached", action="store_true", help="Use cached results when available")

    # gate
    gate_parser = subparsers.add_parser("gate", help="Run local quality gates (Pint, PHPStan, Pest) through snip")
    gate_parser.add_argument("--no-snip", action="store_true", help="Bypass snip filter")

    # pr
    pr_parser = subparsers.add_parser("pr", help="Triage a Pull Request and its CI status")
    pr_parser.add_argument("number", type=int, nargs="?", default=None, help="PR number (default: latest open PR)")

    # ci-wait / ci
    ci_parser = subparsers.add_parser("ci-wait", help="Single-shot CI status check")
    ci_parser.add_argument("--branch", type=str, default=None, help="Branch name (default: current git branch)")

    # test-triage
    subparsers.add_parser("test-triage", help="Batch analyze and cluster Pest test failures")

    # snip-setup / snip-verify
    subparsers.add_parser("snip-setup", help="Register + trust this repo's snip filters, then verify them")
    subparsers.add_parser("snip-verify", help="Run the inline tests of this repo's snip filters")

    return parser


def main(args: list[str] | None = None) -> int:
    parser = create_parser()

    # If no args or -h / --help / help, emit the decision matrix
    if args is not None and (len(args) == 0 or args[0] in ("-h", "--help", "help")):
        print(json.dumps(HELP_DATA, indent=2))
        return 0
    elif args is None and len(sys.argv) == 1:
        print(json.dumps(HELP_DATA, indent=2))
        return 0

    parsed_args = parser.parse_args(args)
    cmd = parsed_args.subcommand
    root = parsed_args.root

    if not cmd or cmd == "help":
        print(json.dumps(HELP_DATA, indent=2))
        return 0

    output: dict[str, Any] = {}
    exit_code = 0

    if cmd == "status":
        output = get_status(repo_root=root, fresh=not parsed_args.cached)
        exit_code = 0

    elif cmd == "gate":
        output = run_gate(repo_root=root, use_snip=not parsed_args.no_snip)
        exit_code = int(output.get("exit_code", 0))

    elif cmd == "pr":
        output = triage_pr(pr_number=parsed_args.number, repo_root=root)
        ci_status = output.get("ci_status")
        ci_conclusion = ci_status.get("conclusion", "") if isinstance(ci_status, dict) else ""
        exit_code = _ci_exit_code(ci_conclusion)

    elif cmd == "ci-wait":
        output = check_ci(branch=parsed_args.branch, repo_root=root)
        exit_code = _ci_exit_code(str(output.get("conclusion", "")), str(output.get("status", "")))

    elif cmd == "test-triage":
        output = triage_tests(repo_root=root)
        raw_ec = output.get("exit_code", 0)
        exit_code = int(raw_ec) if isinstance(raw_ec, (int, str)) else 0

    elif cmd == "snip-setup":
        output = setup_snip(repo_root=root)
        exit_code = int(output.get("exit_code", 0))

    elif cmd == "snip-verify":
        output = verify_snip(repo_root=root)
        exit_code = int(output.get("exit_code", 0))

    # Emit JSON
    print(json.dumps(output, indent=2))
    return exit_code


if __name__ == "__main__":
    sys.exit(main())
