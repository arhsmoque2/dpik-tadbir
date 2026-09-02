#!/usr/bin/env bash
set -uo pipefail

echo "=========================================================="
echo "  DPIK Tadbir: Cloud Sandbox Agent Automated Bootstrap    "
echo "  (Governed by ADR-020: Multi-Tier Resilient Hydration)  "
echo "=========================================================="

REPO_SLUG="arhsmoque2/dpik-tadbir"
RELEASE_TAG="sandbox-vendor-latest"

is_plausible_github_token() {
  local token="${1:-}"
  # Reject empty strings and known proxy/placeholder values
  if [[ -z "$token" || "$token" == "proxy-injected" || "$token" == "dummy" || "$token" == "test" || "$token" == "none" || "$token" == "false" || "$token" == "undefined" ]]; then
    return 1
  fi
  # Recognized GitHub token patterns (PATs, fine-grained, OAuth, installation, or 40-char hex)
  if [[ "$token" =~ ^(ghp_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{82,}|gh[osru]_[A-Za-z0-9]{36,}|[0-9a-fA-F]{40})$ ]]; then
    return 0
  fi
  # Fallback length check for enterprise/custom tokens
  if [[ ${#token} -ge 30 && ! "$token" =~ [[:space:]] ]]; then
    return 0
  fi
  return 1
}

# ------------------------------------------------------------------------------
# STEP 1: HYDRATE PHP VENDOR DEPENDENCIES
# ------------------------------------------------------------------------------
PHP_HYDRATED=false
if [ -f "vendor/autoload.php" ]; then
  echo "--> [Tier 0] vendor/ already populated. Skipping download."
  PHP_HYDRATED=true
else
  echo "--> [Tier 1] Attempting instant pre-compiled vendor bundle download..."
  VENDOR_URL="https://github.com/${REPO_SLUG}/releases/download/${RELEASE_TAG}/vendor.tar.gz"

  if curl -sL --fail --head "$VENDOR_URL" > /dev/null 2>&1; then
    echo "--> [Tier 1] Downloading and unpacking vendor.tar.gz..."
    if curl -sL "$VENDOR_URL" | tar -xz; then
      echo "--> [Tier 1] vendor/ successfully unpacked in <3s."
      PHP_HYDRATED=true
    else
      echo "--> [Tier 1] Warning: Tar extraction failed. Falling back to Tier 2."
    fi
  else
    echo "--> [Tier 1] Pre-compiled vendor bundle not found (HTTP 404). Falling back to Tier 2 (composer)..."
  fi

  if [ "$PHP_HYDRATED" = false ]; then
    echo "--> [Tier 2] Configuring Composer authentication..."
    AUTH_TOKEN=""
    if is_plausible_github_token "${GITHUB_TOKEN:-}"; then
      AUTH_TOKEN="$GITHUB_TOKEN"
    elif is_plausible_github_token "${GH_TOKEN:-}"; then
      AUTH_TOKEN="$GH_TOKEN"
    elif command -v gh &> /dev/null && gh auth status &> /dev/null; then
      CLI_TOKEN="$(gh auth token 2>/dev/null || true)"
      if is_plausible_github_token "$CLI_TOKEN"; then
        AUTH_TOKEN="$CLI_TOKEN"
      fi
    fi

    if [ -n "$AUTH_TOKEN" ]; then
      echo "--> [Tier 2] Valid GitHub token detected. Injecting into Composer config..."
      composer config -g github-oauth.github.com "$AUTH_TOKEN" >/dev/null 2>&1 || true
    else
      echo "--> [Tier 2] Notice: No usable GitHub token detected. Proceeding unauthenticated."
      # Clear any stale/invalid token in global config to prevent 401 errors
      composer config -g --unset github-oauth.github.com >/dev/null 2>&1 || true
    fi

    echo "--> [Tier 2] Running composer install..."
    if composer install --prefer-dist --no-interaction --no-progress; then
      PHP_HYDRATED=true
      echo "--> [Tier 2] Composer install succeeded."
    else
      echo "--> [Tier 3] Composer install failed (API rate limit or network policy)."
      echo "--> [Tier 3] Entering Degraded CI-Feedback Mode for PHP stack (DEVTOOLS.md §4)."
    fi
  fi
fi

# ------------------------------------------------------------------------------
# STEP 2: HYDRATE NODE_MODULES DEPENDENCIES
# ------------------------------------------------------------------------------
NODE_HYDRATED=false
if [ -d "node_modules" ]; then
  echo "--> [Tier 0] node_modules/ already populated. Skipping download."
  NODE_HYDRATED=true
else
  echo "--> [Tier 1] Attempting instant pre-compiled node_modules bundle download..."
  NODE_URL="https://github.com/${REPO_SLUG}/releases/download/${RELEASE_TAG}/node_modules.tar.gz"

  if curl -sL --fail --head "$NODE_URL" > /dev/null 2>&1; then
    echo "--> [Tier 1] Downloading and unpacking node_modules.tar.gz..."
    if curl -sL "$NODE_URL" | tar -xz; then
      echo "--> [Tier 1] node_modules/ successfully unpacked in <3s."
      NODE_HYDRATED=true
    fi
  fi

  if [ "$NODE_HYDRATED" = false ]; then
    echo "--> [Tier 2] Falling back to pnpm install..."
    if command -v pnpm &> /dev/null; then
      pnpm install || echo "--> [Warning] pnpm install encountered issues."
      NODE_HYDRATED=true
    else
      echo "--> [Warning] pnpm not found; skipping node_modules install."
    fi
  fi
fi

# ------------------------------------------------------------------------------
# STEP 3: PLAYWRIGHT CHROMIUM SETUP
# ------------------------------------------------------------------------------
echo "--> Verifying Playwright browser availability..."
if command -v npx &> /dev/null; then
  if [ -w /etc ]; then
    npx playwright install --with-deps chromium 2>/dev/null || npx playwright install chromium 2>/dev/null || true
  else
    npx playwright install chromium 2>/dev/null || true
  fi
fi

# ------------------------------------------------------------------------------
# STEP 4: SQLITE DATABASE & RUNTIME ENVIRONMENT INITIALIZATION
# ------------------------------------------------------------------------------
echo "--> Initializing SQLite datastores and environment..."
if [ ! -f ".env" ]; then
  cp .env.example .env 2>/dev/null || touch .env
fi

mkdir -p database storage/framework/{sessions,views,cache} storage/logs
touch database/database.sqlite
touch database/testing.sqlite

if [ "$PHP_HYDRATED" = true ]; then
  if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force || true
  fi
  php artisan migrate --force || true
else
  echo "--> [Notice] Skipping Artisan migrations until vendor/ is hydrated."
fi

bash "$(dirname "${BASH_SOURCE[0]}")/install-git-hooks.sh" || true

# ------------------------------------------------------------------------------
# SUMMARY REPORT
# ------------------------------------------------------------------------------
echo "=========================================================="
if [ "$PHP_HYDRATED" = true ]; then
  echo "  [READY] Sandbox Environment Successfully Hydrated!      "
  echo "  Commands available:                                     "
  echo "  - composer check:quick  (Pint + FilaCheck + PHPStan L8) "
  echo "  - composer test:diff    (Pest + 90% diff-cover)         "
  echo "  - composer fix          (Auto-fix AST, style, types)   "
  echo "  - bash scripts/sandbox-preflight.sh (All-in-one gate)   "
else
  echo "  [DEGRADED] Sandbox Hydration Incomplete (PHP Vendor)   "
  echo "  - Node & SQLite environments are prepared.              "
  echo "  - For PHP quality gates, follow DEVTOOLS.md §4          "
  echo "    (Degraded CI-Feedback Protocol via GitHub Actions)    "
fi
echo "=========================================================="