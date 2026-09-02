#!/usr/bin/env bash
# Installs this repo's tracked hooks/* into .git/hooks/, so the capability
# gate (and the full preflight on push) actually run locally instead of
# only being documented discipline someone has to remember. Safe to re-run.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOOKS_SRC="${REPO_ROOT}/hooks"
HOOKS_DST="${REPO_ROOT}/.git/hooks"

if [ ! -d "${REPO_ROOT}/.git" ]; then
  echo "--> Not a git checkout (no .git/) — skipping hook install."
  exit 0
fi

mkdir -p "${HOOKS_DST}"

for hook in "${HOOKS_SRC}"/*; do
  name="$(basename "${hook}")"
  cp "${hook}" "${HOOKS_DST}/${name}"
  chmod +x "${HOOKS_DST}/${name}"
  echo "--> Installed ${name} hook."
done

echo "--> Git hooks installed. Skip once with 'git commit/push --no-verify' if you deliberately need to."
