#!/usr/bin/env node

/**
 * DPIK Tadbir: Portable Playwright Deployed Surface Auditor
 * Governed by ADR-015, ADR-016, and ADR-020.
 *
 * Audits:
 * 1. HTTP Status & Mixed-Content TLS Integrity
 * 2. JavaScript Console & Uncaught Runtime Errors
 * 3. Interactive Element Bounding Box Overlaps (AABB)
 * 4. Click Target Obstructions (elementFromPoint Hit-Testing)
 * 5. WCAG 2.1 AA Accessibility Conformance (axe-core)
 * 6. Responsive Visual Artifact Snapshots (Mobile, Tablet, Desktop)
 *
 * Usage:
 *   node scripts/audit-deployed.mjs https://<deployed-domain>/admin
 *   pnpm audit:deployed https://<deployed-domain>/admin
 */

import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import { chromiumLaunchOptions } from './lib/playwright-launch.mjs';

const TARGET_URL = (
  process.argv[2] ||
  process.env.TARGET_URL ||
  process.env.APP_URL ||
  'http://127.0.0.1:8000/admin'
).replace(/\/+$/, '');

const VIEWPORTS = [
  { name: 'Mobile', width: 390, height: 844, maxBlankGap: 120 },
  { name: 'Tablet', width: 768, height: 1024, maxBlankGap: 180 },
  { name: 'Desktop', width: 1280, height: 800, maxBlankGap: 240 },
];

console.log('\n==========================================================');
console.log('  🎭 [DPIK TADBIR] Portable Playwright Deployed Auditor');
console.log(`  Target URL: ${TARGET_URL}`);
console.log('==========================================================\n');

async function runAudit() {
  let totalErrors = 0;
  let totalWarnings = 0;
  const consoleErrors = [];
  const resultsDir = path.resolve(process.cwd(), 'test-results');

  if (!fs.existsSync(resultsDir)) {
    fs.mkdirSync(resultsDir, { recursive: true });
  }

  let browser;
  try {
    browser = await chromium.launch(
      chromiumLaunchOptions({
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
      }),
    );
  } catch (err) {
    console.error('❌ Failed to launch Chromium:', err.message);
    console.error('--> Ensure Playwright browsers are installed: npx playwright install chromium');
    console.error('--> Or set PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH to a pre-installed binary.');
    process.exit(1);
  }

  for (const vp of VIEWPORTS) {
    console.log(`📱 [Viewport Matrix] Testing ${vp.name} (${vp.width}x${vp.height})...`);
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      userAgent: 'Mozilla/5.0 (ARH-Playwright-Deployed-Auditor/1.0)',
      ignoreHTTPSErrors: true,
    });

    const page = await context.newPage();

    // Listen for runtime console errors
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        consoleErrors.push(`[${vp.name}][Console] ${text}`);
      }
    });

    page.on('pageerror', (err) => {
      consoleErrors.push(`[${vp.name}][Uncaught Exception] ${err.message}`);
    });

    try {
      const response = await page.goto(TARGET_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
      const status = response ? response.status() : 0;

      if (status >= 400) {
        console.error(`  ❌ HTTP Status Error: Page returned HTTP ${status}`);
        totalErrors++;
      } else {
        console.log(`  ✅ HTTP Status: ${status} OK`);
      }

      // Settle network & Livewire/Alpine render
      await page.waitForTimeout(1500);

      // --- CHECK 1: Mixed Content Detection ---
      if (TARGET_URL.startsWith('https://')) {
        const mixedContentViolations = await page.evaluate(() => {
          const insecure = [];
          const elements = document.querySelectorAll('[src], [href], form[action]');
          for (const el of elements) {
            const attr = el.getAttribute('src') || el.getAttribute('href') || el.getAttribute('action') || '';
            if (attr.startsWith('http://') && !attr.includes('localhost') && !attr.includes('127.0.0.1')) {
              insecure.push({ tag: el.tagName.toLowerCase(), url: attr });
            }
          }
          return insecure;
        });

        if (mixedContentViolations.length === 0) {
          console.log('  ✅ [Mixed Content] 0 insecure http:// asset references detected.');
        } else {
          console.error(`  ❌ [Mixed Content] Detected ${mixedContentViolations.length} insecure reference(s):`);
          for (const m of mixedContentViolations.slice(0, 3)) {
            console.error(`     - Insecure <${m.tag}> URL: ${m.url}`);
          }
          totalErrors += mixedContentViolations.length;
        }
      }

      // --- CHECK 2: WCAG 2.1 AA Accessibility Conformance (Axe) ---
      try {
        const axeResults = await new AxeBuilder({ page })
          .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
          .analyze();

        if (axeResults.violations.length === 0) {
          console.log('  ✅ [A11y Audit] 0 WCAG 2.1 AA violations.');
        } else {
          console.warn(`  ⚠️ [A11y Audit] Found ${axeResults.violations.length} accessibility violation(s):`);
          for (const v of axeResults.violations.slice(0, 3)) {
            console.warn(`     - [${v.impact || 'minor'}] ${v.id}: ${v.description} (${v.nodes.length} node(s))`);
          }
          totalWarnings += axeResults.violations.length;
        }
      } catch (axeErr) {
        console.warn('  ⚠️ [A11y Audit] Axe scan skipped or failed:', axeErr.message);
      }

      // --- CHECK 3: Bounding Box Collision & Overlap Detection (AABB) ---
      const overlaps = await page.evaluate(() => {
        const selector = 'button, input, select, a, .fi-btn, .fi-ta-header-ctn, .fi-ta-content';
        const elements = Array.from(document.querySelectorAll(selector)).filter((el) => {
          const style = window.getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
          const rect = el.getBoundingClientRect();
          return rect.width > 0 && rect.height > 0;
        });

        const collisions = [];
        for (let i = 0; i < elements.length; i++) {
          for (let j = i + 1; j < elements.length; j++) {
            const a = elements[i];
            const b = elements[j];
            if (a.contains(b) || b.contains(a)) continue;

            const rA = a.getBoundingClientRect();
            const rB = b.getBoundingClientRect();

            const hasIntersection = !(
              rA.right <= rB.left ||
              rA.left >= rB.right ||
              rA.bottom <= rB.top ||
              rA.top >= rB.bottom
            );

            if (hasIntersection) {
              const overlapW = Math.max(0, Math.min(rA.right, rB.right) - Math.max(rA.left, rB.left));
              const overlapH = Math.max(0, Math.min(rA.bottom, rB.bottom) - Math.max(rA.top, rB.top));
              const overlapArea = overlapW * overlapH;
              if (overlapArea > 48) {
                collisions.push({
                  elementA: a.tagName.toLowerCase() + (a.className ? '.' + a.className.split(' ')[0] : ''),
                  elementB: b.tagName.toLowerCase() + (b.className ? '.' + b.className.split(' ')[0] : ''),
                  overlapArea: Math.round(overlapArea),
                });
              }
            }
          }
        }
        return collisions;
      });

      if (overlaps.length === 0) {
        console.log('  ✅ [Overlap Check] 0 unintended interactive element overlaps.');
      } else {
        console.warn(`  ⚠️ [Overlap Check] Found ${overlaps.length} element overlap(s):`);
        for (const o of overlaps.slice(0, 3)) {
          console.warn(`     - Overlap: ${o.elementA} with ${o.elementB} (${o.overlapArea}px²)`);
        }
        totalWarnings += overlaps.length;
      }

      // --- CHECK 4: Hit-Testing (Click Obstruction) ---
      const hitTestResults = await page.evaluate(() => {
        const interactive = Array.from(
          document.querySelectorAll('button:not([disabled]), a[href], input[type="submit"]')
        ).filter((el) => {
          const style = window.getComputedStyle(el);
          if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') return false;
          const r = el.getBoundingClientRect();
          return (
            r.width > 0 &&
            r.height > 0 &&
            r.top >= 0 &&
            r.bottom <= window.innerHeight &&
            r.left >= 0 &&
            r.right <= window.innerWidth
          );
        });

        const blocked = [];
        for (const btn of interactive) {
          const r = btn.getBoundingClientRect();
          const cx = r.left + r.width / 2;
          const cy = r.top + r.height / 2;
          const topElement = document.elementFromPoint(cx, cy);
          if (!topElement) continue;

          const isAccessible = btn === topElement || btn.contains(topElement) || topElement.contains(btn);
          if (!isAccessible) {
            blocked.push({
              target: btn.tagName.toLowerCase() + (btn.id ? '#' + btn.id : ''),
              blockedBy: topElement.tagName.toLowerCase() + (topElement.id ? '#' + topElement.id : ''),
            });
          }
        }
        return blocked;
      });

      if (hitTestResults.length === 0) {
        console.log('  ✅ [Hit-Testing] 0 click obstructions detected.');
      } else {
        console.warn(`  ⚠️ [Hit-Testing] Found ${hitTestResults.length} obstructed click target(s):`);
        for (const h of hitTestResults.slice(0, 3)) {
          console.warn(`     - Target ${h.target} obstructed by ${h.blockedBy}`);
        }
        totalWarnings += hitTestResults.length;
      }

      // --- CHECK 5: Screenshot Artifact Capture ---
      const screenshotFile = path.join(resultsDir, `audit-${vp.name.toLowerCase()}.png`);
      await page.screenshot({ path: screenshotFile, fullPage: true });
      console.log(`  📸 [Screenshot] Saved snapshot: ${screenshotFile}\n`);
    } catch (err) {
      console.error(`  ❌ Error auditing ${vp.name}:`, err.message);
      totalErrors++;
    } finally {
      await page.close();
      await context.close();
    }
  }

  await browser.close();

  // Print Console Error Summary
  if (consoleErrors.length > 0) {
    console.log('----------------------------------------------------------');
    console.error(`⚠️ Detected ${consoleErrors.length} browser runtime console/page error(s):`);
    for (const ce of consoleErrors.slice(0, 5)) {
      console.error(`   ${ce}`);
    }
    totalWarnings += consoleErrors.length;
  }

  console.log('==========================================================');
  if (totalErrors === 0) {
    console.log(`🎉 [PASS] Deployed Page Audit PASSED (0 errors, ${totalWarnings} warnings).`);
    console.log('==========================================================\n');
    process.exit(0);
  } else {
    console.error(`💥 [FAIL] Deployed Page Audit FAILED with ${totalErrors} error(s).`);
    console.log('==========================================================\n');
    process.exit(1);
  }
}

runAudit();