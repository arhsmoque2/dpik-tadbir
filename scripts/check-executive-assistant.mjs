import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const TARGET_URL = 'https://dpik-tadbir-gmnvf7efyq-as.a.run.app/admin/executive-assistant';
const ARTIFACTS_DIR = 'C:/Users/Abdul Rahman Hilmi/.gemini/antigravity-cli/brain/f2062337-bc96-4b31-a836-ada3aabe2253';
const SCREENSHOTS_DIR = path.join(ARTIFACTS_DIR, 'screenshots/executive-assistant');

if (!fs.existsSync(SCREENSHOTS_DIR)) {
  fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

async function auditExecutiveAssistant() {
  console.log('===============================================================');
  console.log('  🎭 [DPIK TADBIR] Executive Assistant Page Live Playwright Audit');
  console.log(`  Target URL: ${TARGET_URL}`);
  console.log('===============================================================\n');

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    ignoreHTTPSErrors: true,
  });

  const page = await context.newPage();
  const consoleLogs = [];
  const networkEvents = [];

  page.on('console', (msg) => {
    const text = `[CONSOLE ${msg.type().toUpperCase()}] ${msg.text()}`;
    consoleLogs.push(text);
    console.log(`  ${text}`);
  });

  page.on('pageerror', (err) => {
    const text = `[PAGE ERROR] ${err.message}`;
    consoleLogs.push(text);
    console.error(`  ❌ ${text}`);
  });

  page.on('response', (res) => {
    if (res.status() >= 400) {
      console.warn(`  ⚠️ HTTP ${res.status()} on ${res.url()}`);
      networkEvents.push({ status: res.status(), url: res.url() });
    }
  });

  const findings = {
    targetUrl: TARGET_URL,
    finalUrl: '',
    httpStatus: 0,
    title: '',
    authRequired: false,
    elements: {},
    screenshots: [],
  };

  try {
    console.log('📍 Navigating to Executive Assistant page...');
    const response = await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 45000 });
    findings.httpStatus = response ? response.status() : 0;
    findings.finalUrl = page.url();
    findings.title = await page.title();

    console.log(`  Initial HTTP Status: ${findings.httpStatus}`);
    console.log(`  Current URL: ${findings.finalUrl}`);
    console.log(`  Page Title: "${findings.title}"`);

    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '01-landing.png'), fullPage: true });
    findings.screenshots.push('01-landing.png');

    // Handle authentication if redirected to login
    if (page.url().includes('/admin/login')) {
      findings.authRequired = true;
      console.log('  Login required. Authenticating as admin@dpik.com.my...');
      const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email').first();
      const passField = page.locator('input[type="password"], input[name*="password"], input#data\\.password').first();
      const submitBtn = page.locator('button[type="submit"], button.fi-btn').first();

      await emailField.fill('admin@dpik.com.my');
      await passField.fill('password');
      await submitBtn.click();
      await page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 25000 });

      // Navigate back to executive-assistant if not automatically redirected
      if (!page.url().includes('/admin/executive-assistant')) {
        console.log('  Redirecting back to executive-assistant page after login...');
        await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 30000 });
      }
    }

    await page.waitForTimeout(2000);
    findings.finalUrl = page.url();
    findings.title = await page.title();
    console.log(`\n📍 Authenticated View: ${findings.finalUrl} ("${findings.title}")`);

    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '02-executive-assistant-page.png'), fullPage: true });
    findings.screenshots.push('02-executive-assistant-page.png');

    // Extract page body text and main content
    const pageText = await page.locator('main, .fi-main, body').first().innerText().catch(() => '');
    console.log('\n--- Page Text Preview ---');
    console.log(pageText.slice(0, 800));

    // Inspect key components
    const buttons = await page.locator('button').allInnerTexts().catch(() => []);
    const headings = await page.locator('h1, h2, h3, h4').allInnerTexts().catch(() => []);
    const textareas = await page.locator('textarea').count().catch(() => 0);
    const inputs = await page.locator('input').count().catch(() => 0);
    const copilotTrigger = await page.locator('[data-copilot-trigger]').isVisible().catch(() => false);
    const drawerPresent = await page.locator('[data-copilot-drawer]').count().catch(() => 0);

    findings.elements = {
      headings: headings.map(h => h.trim()).filter(Boolean),
      buttons: buttons.map(b => b.trim()).filter(Boolean),
      textareasCount: textareas,
      inputsCount: inputs,
      copilotTriggerVisible: copilotTrigger,
      drawerElementPresent: drawerPresent > 0,
    };

    console.log('\n--- Headings Found ---', findings.elements.headings);
    console.log('--- Buttons Found ---', findings.elements.buttons);
    console.log(`--- Textareas: ${textareas}, Inputs: ${inputs}, Copilot Trigger: ${copilotTrigger} ---`);

    // If there is an interactive prompt input on the page or drawer, test interaction
    const mainTextarea = page.locator('textarea').first();
    if (await mainTextarea.isVisible({ timeout: 3000 }).catch(() => false)) {
      console.log('\n📍 Testing interactive prompt input on page...');
      await mainTextarea.fill('Sila berikan status terkini projek FT264 dan ringkasan komitmen.');
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '03-input-filled.png') });
      
      const sendBtn = page.locator('button[type="submit"], button:has-text("Send"), button:has-text("Hantar")').first();
      if (await sendBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
        console.log('  Clicking Send button...');
        await sendBtn.click();
        await page.waitForTimeout(6000);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '04-response-received.png'), fullPage: true });
      }
    } else if (copilotTrigger) {
      console.log('\n📍 Testing Copilot topbar trigger from Executive Assistant page...');
      await page.locator('[data-copilot-trigger]').first().click();
      await page.waitForTimeout(1500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '03-copilot-drawer-toggled.png'), fullPage: true });
    }

  } catch (err) {
    console.error(`💥 [Error auditing page]: ${err.message}`);
    findings.error = err.message;
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '99-error.png'), fullPage: true }).catch(() => {});
  } finally {
    await browser.close();
  }

  const reportPath = path.join(ARTIFACTS_DIR, 'executive-assistant-audit-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({ findings, consoleLogs, networkEvents }, null, 2));
  console.log(`\n📄 Report written to: ${reportPath}`);
}

auditExecutiveAssistant();
