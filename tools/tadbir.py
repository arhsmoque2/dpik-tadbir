#!/usr/bin/env python3
"""DPIK Tadbir top-level CLI runner.

Usage:
    python tools/tadbir.py status
    python tools/tadbir.py gate
    python tools/tadbir.py pr 34
    python tools/tadbir.py ci-wait
    python tools/tadbir.py test-triage
"""

from __future__ import annotations

import sys
from pathlib import Path

# Add src to path for zero-install direct execution
CLI_SRC = Path(__file__).resolve().parent / "tadbir_cli" / "src"
if str(CLI_SRC) not in sys.path:
    sys.path.insert(0, str(CLI_SRC))

from tadbir.cli import main

if __name__ == "__main__":
    sys.exit(main())
