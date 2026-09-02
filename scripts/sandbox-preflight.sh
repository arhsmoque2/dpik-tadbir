#!/usr/bin/env bash
set -euo pipefail

echo "=========================================================="
echo "  DPIK Tadbir: Hermetic Sandbox Preflight Quality Gate    "
echo "=========================================================="

if [ ! -d "vendor/bin" ]; then
  echo "--> [ERROR] vendor/bin directory not found."
  echo "--> Run 'bash scripts/setup-sandbox.sh' or follow DEVTOOLS.md §4 for Degraded CI-Feedback Protocol."
  exit 1
fi

echo "--> 1. Running deterministic auto-fix cascade..."
vendor/bin/pint || true
vendor/bin/filacheck app/Filament --fix --dirty || true

echo "--> 2. Running static analysis & type safety (PHPStan L8)..."
vendor/bin/phpstan analyse --level=8 --memory-limit=1G

echo "--> 3. Running hermetic Pest test suite with coverage..."
vendor/bin/pest --coverage-clover coverage.xml --parallel

echo "--> 4. Enforcing 90% Diff-Cover gate..."
BASE_BRANCH="${1:-origin/main}"
uvx diff-cover coverage.xml --compare-branch "$BASE_BRANCH" --fail-under 90

echo "--> 5. Checking docs & spec hygiene..."
if command -v pnpm >/dev/null 2>&1; then
  pnpm exec markdownlint-cli2 --config .markdownlint-cli2.jsonc
elif command -v npx >/dev/null 2>&1; then
  npx --yes markdownlint-cli2 --config .markdownlint-cli2.jsonc
else
  echo "--> Node/pnpm not found — skipping docs hygiene check in minimal container."
fi

echo "=========================================================="
echo "  [SUCCESS] All sandbox quality gates passed hermetically! "
echo "=========================================================="