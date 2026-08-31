/**
 * Shared Playwright launch helper for this repo's standalone Node scripts
 * (not the `playwright test` runner used by tests/Browser/, which resolves
 * browsers through its own project config).
 *
 * Ported from ARH-URUS's scripts/lib/playwright-launch.mjs — the same root
 * cause bit dpik-tadbir directly: scripts/audit-deployed.mjs's bare
 * `chromium.launch({ headless: true })` resolved to a chrome-headless-shell
 * binary revision that didn't match what this sandbox's pre-installed
 * Chromium cache actually holds, so it failed with "Executable doesn't
 * exist" the first time this script was run in a Claude Code sandbox.
 * Confirmed working fallback: passing executablePath explicitly at
 * /opt/pw-browsers/chromium (a symlink to the real binary) launches fine.
 */
import fs from 'node:fs';

// Known pre-installed Chromium locations in sandboxed/cloud agent
// containers this repo's scripts have actually been run in. Checked only
// as a last-resort fallback — a real contributor machine or CI runner with
// a normally-resolving Playwright cache never reaches this branch.
const KNOWN_SANDBOX_CHROMIUM_PATHS = ['/opt/pw-browsers/chromium'];

/**
 * Launch options for `chromium.launch()`, honoring an explicit
 * PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH override first, then falling back to
 * auto-detecting a known sandbox install path, then finally Playwright's
 * own default resolution.
 */
export function chromiumLaunchOptions(extra = {}) {
  const options = { headless: true, ...extra };
  const override = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  if (override) {
    options.executablePath = override;
    return options;
  }
  const detected = KNOWN_SANDBOX_CHROMIUM_PATHS.find((p) => fs.existsSync(p));
  if (detected) options.executablePath = detected;
  return options;
}
