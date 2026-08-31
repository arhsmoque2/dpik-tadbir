import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const BASE_URL = 'https://dpik-tadbir-102469945521.asia-southeast1.run.app';
const ARTIFACTS_DIR = 'C:/Users/Abdul Rahman Hilmi/.gemini/antigravity-cli/brain/f2062337-bc96-4b31-a836-ada3aabe2253';
const SCREENSHOTS_DIR = path.join(ARTIFACTS_DIR, 'screenshots');

if (!fs.existsSync(SCREENSHOTS_DIR)) {
  fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

async function runComprehensiveAudit() {
  console.log('===============================================================');
  console.log('  🎭 [DPIK TADBIR] Comprehensive Live Playwright Audit');
  console.log(`  Target Deployed URL: ${BASE_URL}`);
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

  const auditLog = {
    auth: {},
    navigation: {},
    copilot: {},
    chatTurn: {},
    configPage: {},
    probes: {},
    consoleErrors: [],
    networkErrors: [],
  };

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      console.error(`  ❌ [Console Error] ${msg.text()}`);
      auditLog.consoleErrors.push(msg.text());
    } else {
      console.log(`  [Console ${msg.type()}] ${msg.text()}`);
    }
  });

  page.on('pageerror', (err) => {
    console.error(`  ❌ [Uncaught Page Exception] ${err.message}`);
    auditLog.consoleErrors.push(err.message);
  });

  page.on('response', (res) => {
    if (res.status() >= 400) {
      console.warn(`  ⚠️ [HTTP ${res.status()}] ${res.url()}`);
      auditLog.networkErrors.push({ status: res.status(), url: res.url() });
    }
  });

  try {
    // -----------------------------------------------------------------
    // 1. Authentication & Dashboard Landing
    // -----------------------------------------------------------------
    console.log('📍 Phase 1: Authentication & Dashboard Verification...');
    await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle', timeout: 45000 });
    console.log(`  Current URL: ${page.url()}`);

    if (page.url().includes('/admin/login')) {
      console.log('  Logging in as admin@dpik.com.my...');
      const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email').first();
      const passField = page.locator('input[type="password"], input[name*="password"], input#data\\.password').first();
      const submitBtn = page.locator('button[type="submit"], button.fi-btn').first();

      await emailField.fill('admin@dpik.com.my');
      await passField.fill('password');
      await submitBtn.click();
      await page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 20000 });
    }

    await page.waitForTimeout(2000);
    const dashboardTitle = await page.title();
    console.log(`  ✅ Logged in successfully. Title: "${dashboardTitle}"`);
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '01-dashboard.png'), fullPage: true });

    auditLog.auth = {
      status: 'SUCCESS',
      user: 'admin@dpik.com.my',
      url: page.url(),
      title: dashboardTitle,
    };

    // -----------------------------------------------------------------
    // 2. Resource Navigations (Project Register, Notes, Tasks, Presets)
    // -----------------------------------------------------------------
    console.log('\n📍 Phase 2: Auditing Resource Endpoints...');
    const resources = [
      { name: 'Project Registers', path: '/admin/project-registers' },
      { name: 'Personal Notes', path: '/admin/personal-notes' },
      { name: 'Personal Tasks', path: '/admin/personal-tasks' },
      { name: 'Executive Presets', path: '/admin/executive-presets' },
      { name: 'Allowed Emails', path: '/admin/allowed-registration-emails' },
    ];

    for (const r of resources) {
      console.log(`  Checking ${r.name} (${r.path})...`);
      const resp = await page.goto(`${BASE_URL}${r.path}`, { waitUntil: 'networkidle', timeout: 30000 });
      const status = resp ? resp.status() : 0;
      const title = await page.title();
      const rowsCount = await page.locator('.fi-ta-row, tr.fi-ta-row, tbody tr').count().catch(() => 0);
      console.log(`    Status: ${status} | Title: "${title}" | Table Rows: ${rowsCount}`);
      auditLog.navigation[r.name] = { status, title, rowsCount };
    }

    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '02-project-registers.png'), fullPage: true });

    // -----------------------------------------------------------------
    // 3. Testing Executive Settings (Config Page)
    // -----------------------------------------------------------------
    console.log('\n📍 Phase 3: Auditing Executive Settings & AI Integrations...');
    await page.goto(`${BASE_URL}/admin/executive-settings`, { waitUntil: 'networkidle', timeout: 30000 });
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '03-executive-settings.png'), fullPage: true });

    // Inspect models and configuration elements
    const pageText = await page.locator('main').innerText().catch(() => '');
    console.log('  Executive Settings Page loaded successfully.');

    // Look for test probe buttons
    const testAiBtn = page.locator('button:has-text("Test AI Keys")').first();
    const testOpenRouterBtn = page.locator('button:has-text("Test OpenRouter")').first();
    const testOutlookBtn = page.locator('button:has-text("Test Outlook Connection")').first();

    if (await testAiBtn.isVisible()) {
      console.log('  Testing AI Keys Probe...');
      await testAiBtn.click();
      await page.waitForTimeout(2500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '04-probe-ai-keys.png') });
    }

    if (await testOpenRouterBtn.isVisible()) {
      console.log('  Testing OpenRouter Connection Probe...');
      await testOpenRouterBtn.click();
      await page.waitForTimeout(2500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '05-probe-openrouter.png') });
    }

    if (await testOutlookBtn.isVisible()) {
      console.log('  Testing Outlook MCP Connection Probe...');
      await testOutlookBtn.click();
      await page.waitForTimeout(2500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '06-probe-outlook.png') });
    }

    // Capture any notifications or alert boxes
    const notifications = await page.locator('.fi-no-notification, .filament-notifications, [role="status"], [role="alert"]').allInnerTexts().catch(() => []);
    console.log('  Notifications detected:', notifications);

    auditLog.configPage = {
      status: 'VERIFIED',
      notifications,
      hasAnthropicField: pageText.includes('Anthropic API Key'),
      hasGeminiField: pageText.includes('Google Gemini API Key'),
      hasOpenRouterField: pageText.includes('OpenRouter API Key'),
      hasOutlookField: pageText.includes('Microsoft Entra ID'),
    };

    // -----------------------------------------------------------------
    // 4. Copilot Drawer Open & Model Swapper
    // -----------------------------------------------------------------
    console.log('\n📍 Phase 4: Testing Copilot Drawer & Dynamic Runtime Model Swapper...');
    await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle', timeout: 30000 });

    // Open Copilot Drawer via trigger click or window event
    const trigger = page.locator('[data-copilot-trigger]').first();
    if (await trigger.isVisible({ timeout: 5000 })) {
      console.log('  Clicking [data-copilot-trigger]...');
      await trigger.click();
    } else {
      console.log('  Dispatching toggle-copilot-drawer event...');
      await page.evaluate(() => window.dispatchEvent(new CustomEvent('toggle-copilot-drawer')));
    }

    // Wait for drawer to be visible
    const drawer = page.locator('[data-copilot-drawer]').first();
    await drawer.waitFor({ state: 'visible', timeout: 10000 });
    console.log('  ✅ Copilot Drawer is OPEN and visible!');
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '07-copilot-drawer-open.png') });

    // -----------------------------------------------------------------
    // 5. Testing Real Live AI Chat Turn
    // -----------------------------------------------------------------
    console.log('\n📍 Phase 5: Executing Real AI Copilot Chat Turn...');
    const textarea = drawer.locator('textarea').first();
    await textarea.waitFor({ state: 'visible', timeout: 8000 });

    const testPrompt = 'Sila semak rekod projek PC-2023-011 dan berikan ringkasan ringkas.';
    console.log(`  Entering prompt: "${testPrompt}"`);
    await textarea.fill(testPrompt);
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '09-chat-input-entered.png') });

    // Click Send button
    const sendButton = drawer.locator('button[type="submit"]:has-text("Send")').first();
    console.log('  Clicking Send button...');
    await sendButton.click();

    // Wait for processing state and live AI response
    console.log('  Waiting for AI processing response...');
    await page.waitForTimeout(5000);
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '10-chat-in-progress.png') });

    // Poll for completion (up to 30s)
    let completed = false;
    for (let i = 0; i < 8; i++) {
      await page.waitForTimeout(3000);
      const isProcessing = await drawer.locator('text=Analyzing project memory').isVisible().catch(() => false);
      const messagesCount = await drawer.locator('#copilot-message-stream > div').count().catch(() => 0);
      console.log(`    [Turn Poll ${i+1}] Messages in stream: ${messagesCount}, Processing spinner: ${isProcessing}`);
      if (!isProcessing && messagesCount >= 2) {
        completed = true;
        break;
      }
    }

    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '11-chat-response-final.png') });

    // Extract all messages from drawer stream
    const messages = await drawer.locator('#copilot-message-stream').innerText().catch(() => '');
    console.log('\n===============================================================');
    console.log('  🤖 AI COPILOT LIVE RESPONSE STREAM:');
    console.log('===============================================================');
    console.log(messages);
    console.log('===============================================================\n');

    auditLog.chatTurn = {
      prompt: testPrompt,
      completed,
      fullConversationText: messages,
    };

  } catch (err) {
    console.error(`💥 [Error in Audit]: ${err.message}`);
    auditLog.error = err.message;
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '99-error-state.png') }).catch(() => {});
  } finally {
    await browser.close();
  }

  // Save audit log JSON
  const auditReportPath = path.join(ARTIFACTS_DIR, 'live-audit-report.json');
  fs.writeFileSync(auditReportPath, JSON.stringify(auditLog, null, 2));
  console.log(`\n📄 Detailed JSON report written to: ${auditReportPath}`);
}

runComprehensiveAudit();
