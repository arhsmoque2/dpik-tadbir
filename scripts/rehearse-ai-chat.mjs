#!/usr/bin/env node

/**
 * DPIK Tadbir: AI Copilot Drawer Live Rehearsal
 *
 * A focused Playwright rehearsal for the Copilot drawer's actual
 * open -> type -> send -> respond flow, distinct from
 * scripts/audit-deployed.mjs (which checks page-level structural/
 * a11y/visual health, not this specific interactive journey).
 *
 * Grew out of diagnosing two real bugs live rather than by reading the
 * diff: the drawer's Alpine/Livewire double-toggle race (AiCopilotDrawer's
 * #[On('toggle-copilot-drawer')] fighting its own Alpine x-data listener),
 * and LlmGatewayService silently falling through to a canned mock reply
 * for any unconfigured provider instead of surfacing a real error. Kept
 * as a reusable script rather than deleted after use — the same rehearsal
 * is worth re-running any time the drawer or AgentService's error paths
 * change.
 *
 * Usage:
 *   node scripts/rehearse-ai-chat.mjs [baseUrl] ["question text"]
 *   pnpm rehearse:ai-chat [baseUrl] ["question text"]
 *
 * baseUrl defaults to http://127.0.0.1:8000 (a locally running
 * `php artisan serve`) — this script drives real browser interactions
 * against a real app instance, it does not stub anything.
 */

import { chromium } from '@playwright/test';
import { chromiumLaunchOptions } from './lib/playwright-launch.mjs';

const BASE_URL = (process.argv[2] || process.env.APP_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const QUESTION = process.argv[3] || 'What is the status of Sungai Udang?';

console.log(`\n🎭 [DPIK TADBIR] AI Copilot Drawer Rehearsal — target: ${BASE_URL}/admin\n`);

const browser = await chromium.launch(chromiumLaunchOptions({ args: ['--no-sandbox'] }));
const page = await browser.newPage();
const consoleMsgs = [];
page.on('console', (m) => consoleMsgs.push(`[${m.type()}] ${m.text()}`));
page.on('pageerror', (e) => consoleMsgs.push(`[pageerror] ${e.message}`));

await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle' });
console.log('Logged-in user visible:', await page.locator('body').isVisible());
await page.screenshot({ path: 'test-results/rehearse-1-dashboard.png', fullPage: true });

const trigger = page.locator('[data-copilot-trigger]');
console.log('Copilot trigger count:', await trigger.count());
console.log('Copilot trigger visible:', await trigger.first().isVisible().catch(() => false));

await trigger.first().click();
await page.waitForTimeout(800); // let the double-toggle race (if any) resolve
const drawer = page.locator('[data-copilot-drawer]');
console.log('Drawer visible after 1 click + 800ms:', await drawer.first().isVisible().catch(() => false));
await page.screenshot({ path: 'test-results/rehearse-2-after-click.png', fullPage: true });

// Try again after a longer settle, in case it's a timing race
await page.waitForTimeout(1500);
const drawerOpen = await drawer.first().isVisible().catch(() => false);
console.log('Drawer visible after 1 click + 2300ms total:', drawerOpen);

if (drawerOpen) {
  const textarea = drawer.locator('textarea').first();
  await textarea.fill(QUESTION);
  await page.screenshot({ path: 'test-results/rehearse-3-before-send.png', fullPage: true });

  const sendBtn = drawer.locator('button[type="submit"], button:has-text("Send")').first();
  if (await sendBtn.count()) {
    await sendBtn.click();
  } else {
    await textarea.press('Enter');
  }

  await page.waitForTimeout(4000);
  await page.screenshot({ path: 'test-results/rehearse-4-after-send.png', fullPage: true });
  const drawerText = await drawer.first().innerText().catch(() => '(could not read drawer text)');
  console.log('--- Drawer text after send ---');
  console.log(drawerText.slice(0, 2000));
} else {
  console.log('Drawer never became visible — skipping send-message step.');
}

console.log('--- Console/page errors captured ---');
console.log(consoleMsgs.join('\n') || '(none)');

await browser.close();
