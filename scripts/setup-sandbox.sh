#!/usr/bin/env bash
set -euo pipefail

echo "=========================================================="
echo "  DPIK Tadbir: Cloud Sandbox Agent Automated Bootstrap    "
echo "  (Governed by ADR-020: Two-Tier Dependency Hydration)   "
echo "=========================================================="

REPO_SLUG="arhsmoque2/dpik-tadbir"
RELEASE_TAG="sandbox-vendor-latest"

# ------------------------------------------------------------------------------
# STEP 1: HYDRATE PHP VENDOR DEPENDENCIES
# ------------------------------------------------------------------------------
if [ -f "vendor/autoload.php" ]; then
  echo "--> [Tier 0] vendor/ already populated. Skipping download."
else
  echo "--> [Tier 1] Attempting instant pre-compiled vendor bundle download..."
  VENDOR_URL="https://github.com/${REPO_SLUG}/releases/download/${RELEASE_TAG}/vendor.tar.gz"
  
  if curl -sL --fail --head "$VENDOR_URL" > /dev/null 2>&1; then
    echo "--> [Tier 1] Downloading and unpacking vendor.tar.gz..."
    curl -sL "$VENDOR_URL" | tar -xz
    echo "--> [Tier 1] vendor/ successfully unpacked in <3s."
  else
    echo "--> [Tier 1] Pre-compiled vendor bundle not found. Falling back to Tier 2 (composer install)..."
    
    # Inject token to bypass GitHub API 403 rate limits
    if [ -n "${GITHUB_TOKEN:-}" ]; then
      echo "--> [Tier 2] Injecting GITHUB_TOKEN into Composer config..."
      composer config -g github-oauth.github.com "$GITHUB_TOKEN"
    elif [ -n "${GH_TOKEN:-}" ]; then
      echo "--> [Tier 2] Injecting GH_TOKEN into Composer config..."
      composer config -g github-oauth.github.com "$GH_TOKEN"
    elif command -v gh &> /dev/null && gh auth status &> /dev/null; then
      echo "--> [Tier 2] Injecting gh CLI auth token into Composer config..."
      composer config -g github-oauth.github.com "$(gh auth token)"
    else
      echo "--> [Tier 2] Warning: No GitHub auth token found. Composer may hit API rate limits."
    fi
    
    composer install --prefer-dist --no-interaction --no-progress
  fi
fi

# ------------------------------------------------------------------------------
# STEP 2: HYDRATE NODE_MODULES DEPENDENCIES
# ------------------------------------------------------------------------------
if [ -d "node_modules" ]; then
  echo "--> [Tier 0] node_modules/ already populated. Skipping download."
else
  echo "--> [Tier 1] Attempting instant pre-compiled node_modules bundle download..."
  NODE_URL="https://github.com/${REPO_SLUG}/releases/download/${RELEASE_TAG}/node_modules.tar.gz"
  
  if curl -sL --fail --head "$NODE_URL" > /dev/null 2>&1; then
    echo "--> [Tier 1] Downloading and unpacking node_modules.tar.gz..."
    curl -sL "$NODE_URL" | tar -xz
    echo "--> [Tier 1] node_modules/ successfully unpacked in <3s."
  else
    echo "--> [Tier 1] Falling back to Tier 2 (pnpm install)..."
    pnpm install
  fi
fi

# ------------------------------------------------------------------------------
# STEP 3: PLAYWRIGHT CHROMIUM SETUP
# ------------------------------------------------------------------------------
echo "--> Verifying Playwright browser availability..."
if command -v npx &> /dev/null; then
  if [ -w /etc ]; then
    npx playwright install --with-deps chromium || npx playwright install chromium || true
  else
    npx playwright install chromium || true
  fi
fi

# ------------------------------------------------------------------------------
# STEP 4: SQLITE DATABASE & RUNTIME ENVIRONMENT INITIALIZATION
# ------------------------------------------------------------------------------
echo "--> Initializing SQLite datastores and environment..."
if [ ! -f ".env" ]; then
  cp .env.example .env
fi

mkdir -p database storage/framework/{sessions,views,cache} storage/logs
touch database/database.sqlite
touch database/testing.sqlite

if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

php artisan migrate --force

echo "=========================================================="
echo "  [READY] Sandbox Environment Successfully Hydrated!      "
echo "  Commands available:                                     "
echo "  - composer check:quick  (Pint + FilaCheck + PHPStan L8) "
echo "  - composer test:diff    (Pest + 90% diff-cover)         "
echo "  - composer fix          (Auto-fix AST, style, types)   "
echo "  - bash scripts/sandbox-preflight.sh (All-in-one gate)   "
echo "=========================================================="