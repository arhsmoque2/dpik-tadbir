import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const TARGET_URL = 'https://dpik-tadbir-gmnvf7efyq-as.a.run.app/admin/executive-settings';
const ARTIFACTS_DIR = 'C:/Users/Abdul Rahman Hilmi/.gemini/antigravity-cli/brain/f2062337-bc96-4b31-a836-ada3aabe2253';
const SCREENSHOTS_DIR = path.join(ARTIFACTS_DIR, 'screenshots/executive-settings');

if (!fs.existsSync(SCREENSHOTS_DIR)) {
  fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

async function auditExecutiveSettings() {
  console.log('===============================================================');
  console.log('  🎭 [DPIK TADBIR] Executive Settings Live Playwright Audit');
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
    fields: {},
    probes: {},
    buttons: [],
    screenshots: [],
  };

  try {
    console.log('📍 Navigating to Executive Settings page...');
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

      if (!page.url().includes('/admin/executive-settings')) {
        console.log('  Redirecting back to executive-settings page after login...');
        await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 30000 });
      }
    }

    await page.waitForTimeout(2000);
    findings.finalUrl = page.url();
    findings.title = await page.title();
    console.log(`\n📍 Authenticated View: ${findings.finalUrl} ("${findings.title}")`);

    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '02-settings-page.png'), fullPage: true });
    findings.screenshots.push('02-settings-page.png');

    // Extract page body text
    const pageText = await page.locator('main, .fi-main, body').first().innerText().catch(() => '');
    console.log('\n--- Settings Page Text Preview ---');
    console.log(pageText.slice(0, 800));

    // Inspect inputs and selects
    const inputs = await page.locator('input').all();
    const selects = await page.locator('select').all();
    const buttons = await page.locator('button').allInnerTexts().catch(() => []);
    findings.buttons = buttons.map(b => b.trim()).filter(Boolean);

    console.log('\n--- Buttons Found ---', findings.buttons);

    // Test Probes:
    // 1. Test AI Keys
    const testAiBtn = page.locator('button:has-text("Test AI Keys")').first();
    if (await testAiBtn.isVisible({ timeout: 2000 })) {
      console.log('📍 Testing "Test AI Keys" button...');
      await testAiBtn.click();
      await page.waitForTimeout(2500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '03-probe-ai-keys.png') });
      findings.screenshots.push('03-probe-ai-keys.png');
    }

    // 2. Test OpenRouter
    const testOpenRouterBtn = page.locator('button:has-text("Test OpenRouter")').first();
    if (await testOpenRouterBtn.isVisible({ timeout: 2000 })) {
      console.log('📍 Testing "Test OpenRouter" button...');
      await testOpenRouterBtn.click();
      await page.waitForTimeout(2500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '04-probe-openrouter.png') });
      findings.screenshots.push('04-probe-openrouter.png');
    }

    // 3. Test Outlook Connection
    const testOutlookBtn = page.locator('button:has-text("Test Outlook Connection")').first();
    if (await testOutlookBtn.isVisible({ timeout: 2000 })) {
      console.log('📍 Testing "Test Outlook Connection" button...');
      await testOutlookBtn.click();
      await page.waitForTimeout(2500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '05-probe-outlook.png') });
      findings.screenshots.push('05-probe-outlook.png');
    }

    // Capture notification toasts
    const notifications = await page.locator('.fi-no-notification, .filament-notifications, [role="status"], [role="alert"]').allInnerTexts().catch(() => []);
    findings.probes.notifications = notifications.map(n => n.trim()).filter(Boolean);
    console.log('\n--- Captured Notification Feedback ---', findings.probes.notifications);

    // Save All Settings test
    const saveBtn = page.locator('button:has-text("Save All Settings")').first();
    if (await saveBtn.isVisible({ timeout: 2000 })) {
      console.log('📍 Testing "Save All Settings" button...');
      await saveBtn.click();
      await page.waitForTimeout(3000);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '06-settings-saved.png'), fullPage: true });
      findings.screenshots.push('06-settings-saved.png');
    }

  } catch (err) {
    console.error(`💥 [Error auditing page]: ${err.message}`);
    findings.error = err.message;
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '99-error.png'), fullPage: true }).catch(() => {});
  } finally {
    await browser.close();
  }

  const reportPath = path.join(ARTIFACTS_DIR, 'executive-settings-audit-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({ findings, consoleLogs, networkEvents }, null, 2));
  console.log(`\n📄 Report written to: ${reportPath}`);
}

auditExecutiveSettings();
