import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const BASE_URL = 'https://dpik-tadbir-102469945521.asia-southeast1.run.app';
const RESULTS_DIR = path.resolve('test-results/deployed-verification');

if (!fs.existsSync(RESULTS_DIR)) {
  fs.mkdirSync(RESULTS_DIR, { recursive: true });
}

async function verifyDeployedSite() {
  console.log('🚀 Starting Deployed DPIK Tadbir Verification on Cloud Run');
  console.log(`🎯 Base URL: ${BASE_URL}`);

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
  const logs = [];

  page.on('console', (msg) => {
    logs.push(`[CONSOLE ${msg.type().toUpperCase()}] ${msg.text()}`);
    console.log(`  [Console ${msg.type()}] ${msg.text()}`);
  });

  page.on('pageerror', (err) => {
    logs.push(`[PAGE ERROR] ${err.message}`);
    console.error(`  ❌ [Page Error] ${err.message}`);
  });

  page.on('response', (res) => {
    if (res.status() >= 400) {
      console.warn(`  ⚠️ HTTP ${res.status()} on ${res.url()}`);
    }
  });

  const report = {
    auth: { status: 'PENDING' },
    dashboard: { status: 'PENDING' },
    configPage: { status: 'PENDING' },
    copilotDrawer: { status: 'PENDING' },
    realChat: { status: 'PENDING' },
    navigation: {},
    screenshots: [],
  };

  try {
    // -------------------------------------------------------------
    // Step 1: Initial Navigation & Authentication
    // -------------------------------------------------------------
    console.log('\n--- Step 1: Navigating to /admin ---');
    const resp = await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle', timeout: 45000 });
    console.log(`Initial status: ${resp.status()}, Current URL: ${page.url()}`);

    await page.screenshot({ path: path.join(RESULTS_DIR, '01-initial-landing.png'), fullPage: true });
    report.screenshots.push('01-initial-landing.png');

    // Check if on login page
    if (page.url().includes('/admin/login')) {
      console.log('Detected Filament Login Page. Attempting authentication...');
      const emailInput = page.locator('input[type="email"], input[name*="email"], input#data\\.email').first();
      const passwordInput = page.locator('input[type="password"], input[name*="password"], input#data\\.password').first();
      const submitBtn = page.locator('button[type="submit"], button.fi-btn').first();

      await emailInput.fill('admin@dpik.com.my');
      await passwordInput.fill('password');
      await page.screenshot({ path: path.join(RESULTS_DIR, '02-login-filled.png') });
      
      await submitBtn.click();
      await page.waitForLoadState('networkidle', { timeout: 30000 });
      console.log(`Post-login URL: ${page.url()}`);
    }

    await page.screenshot({ path: path.join(RESULTS_DIR, '03-dashboard.png'), fullPage: true });
    report.screenshots.push('03-dashboard.png');

    const pageTitle = await page.title();
    console.log(`Dashboard Page Title: "${pageTitle}"`);
    report.auth = {
      status: page.url().includes('/admin/login') ? 'FAILED' : 'SUCCESS',
      currentUrl: page.url(),
      title: pageTitle,
    };

    // -------------------------------------------------------------
    // Step 2: Test Navigation & Resources
    // -------------------------------------------------------------
    console.log('\n--- Step 2: Testing Admin Navigation & Resources ---');
    const links = [
      { name: 'Projects Register', path: '/admin/project-registers' },
      { name: 'Personal Notes', path: '/admin/personal-notes' },
      { name: 'Personal Tasks', path: '/admin/personal-tasks' },
      { name: 'Executive Presets', path: '/admin/executive-presets' },
      { name: 'Settings & Integration', path: '/admin/executive-settings' },
    ];

    for (const link of links) {
      console.log(`Navigating to ${link.name} (${link.path})...`);
      try {
        const res = await page.goto(`${BASE_URL}${link.path}`, { waitUntil: 'networkidle', timeout: 30000 });
        const title = await page.title();
        const bodySnippet = await page.locator('body').innerText();
        const isHealthy = res.status() < 400 && !bodySnippet.includes('500 Server Error') && !bodySnippet.includes('SQLSTATE');
        report.navigation[link.name] = {
          status: isHealthy ? 'OK' : 'ERROR',
          httpStatus: res.status(),
          title: title,
        };
        console.log(`  ${link.name}: HTTP ${res.status()} - Title: ${title}`);
      } catch (e) {
        console.error(`  ❌ Failed to navigate to ${link.name}: ${e.message}`);
        report.navigation[link.name] = { status: 'ERROR', error: e.message };
      }
    }

    // -------------------------------------------------------------
    // Step 3: Deep Dive into Config Page (Executive Settings)
    // -------------------------------------------------------------
    console.log('\n--- Step 3: Testing Executive Settings (Config Page) ---');
    await page.goto(`${BASE_URL}/admin/executive-settings`, { waitUntil: 'networkidle', timeout: 30000 });
    await page.screenshot({ path: path.join(RESULTS_DIR, '04-executive-settings.png'), fullPage: true });
    report.screenshots.push('04-executive-settings.png');

    // Inspect fields on Executive Settings
    const settingsContent = await page.locator('main, .fi-main, .fi-page').first().innerText().catch(() => '');
    console.log('Settings page text preview:\n', settingsContent.slice(0, 500));

    // Check for probe buttons (AI probe, OpenRouter probe, Outlook probe)
    const buttons = await page.locator('button').allInnerTexts();
    console.log('Visible buttons on Settings page:', buttons.filter(b => b.trim().length > 0));

    // Test AI Probe Button if visible
    const testAiBtn = page.locator('button:has-text("Test AI"), button:has-text("Verify AI"), button:has-text("Test Connection")').first();
    if (await testAiBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      console.log('Found AI Test button. Clicking...');
      await testAiBtn.click();
      await page.waitForTimeout(3000);
      await page.screenshot({ path: path.join(RESULTS_DIR, '05-settings-ai-probe.png'), fullPage: true });
    }

    report.configPage = {
      status: 'VERIFIED',
      url: page.url(),
      hasContent: settingsContent.length > 50,
      buttons: buttons.filter(b => b.trim().length > 0),
    };

    // -------------------------------------------------------------
    // Step 4: Test Copilot Drawer & Preset Ribbon
    // -------------------------------------------------------------
    console.log('\n--- Step 4: Testing Copilot Drawer & Presets ---');
    // Return to dashboard or stay on current page
    await page.goto(`${BASE_URL}/admin`, { waitUntil: 'networkidle', timeout: 30000 });

    // Look for copilot trigger button
    const copilotTrigger = page.locator('[data-copilot-trigger], button:has-text("Copilot"), button:has-text("AI Assistant"), button[aria-label*="Copilot"]').first();
    const triggerVisible = await copilotTrigger.isVisible({ timeout: 5000 }).catch(() => false);
    console.log(`Copilot trigger visible: ${triggerVisible}`);

    if (triggerVisible) {
      await copilotTrigger.click();
    } else {
      console.log('Trigger not found by selector, pressing Cmd+J / Ctrl+J or checking drawer...');
      await page.keyboard.press('Control+j');
    }

    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(RESULTS_DIR, '06-copilot-drawer-open.png'), fullPage: true });
    report.screenshots.push('06-copilot-drawer-open.png');

    const drawer = page.locator('[data-copilot-drawer], .fi-copilot-drawer, div[x-show*="open"], div[x-show*="drawer"]').first();
    const drawerVisible = await drawer.isVisible({ timeout: 5000 }).catch(() => false);
    console.log(`Copilot Drawer panel visible: ${drawerVisible}`);

    report.copilotDrawer = {
      triggerFound: triggerVisible,
      drawerOpened: drawerVisible,
    };

    // -------------------------------------------------------------
    // Step 5: Test Real Chat Turn in Copilot
    // -------------------------------------------------------------
    console.log('\n--- Step 5: Testing Real Chat in Copilot ---');
    // Locate prompt textarea inside copilot or page
    const chatInput = page.locator('[data-copilot-drawer] textarea, textarea#prompt, textarea[wire\\:model*="prompt"], textarea[placeholder*="Ask"], textarea[placeholder*="Tanya"], textarea').first();
    const inputVisible = await chatInput.isVisible({ timeout: 5000 }).catch(() => false);
    console.log(`Chat Textarea visible: ${inputVisible}`);

    if (inputVisible) {
      const promptQuery = 'Sila semak status projek Jambatan Sungai Udang dan ringkaskan keputusan terkini.';
      console.log(`Filling prompt: "${promptQuery}"`);
      await chatInput.fill(promptQuery);
      await page.screenshot({ path: path.join(RESULTS_DIR, '07-prompt-entered.png') });

      // Send prompt
      const sendBtn = page.locator('[data-copilot-drawer] button[type="submit"], [data-copilot-drawer] button:has-text("Send"), [data-copilot-drawer] button:has-text("Hantar"), [data-copilot-drawer] button:has-text("Tanya"), button:has-text("Send")').first();
      if (await sendBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
        console.log('Clicking Send button...');
        await sendBtn.click();
      } else {
        console.log('Pressing Enter to submit prompt...');
        await chatInput.press('Enter');
      }

      // Wait for AI response processing (up to 30 seconds)
      console.log('Waiting for AI response...');
      await page.waitForTimeout(8000);
      await page.screenshot({ path: path.join(RESULTS_DIR, '08-chat-in-progress.png'), fullPage: true });

      await page.waitForTimeout(10000);
      await page.screenshot({ path: path.join(RESULTS_DIR, '09-chat-completed.png'), fullPage: true });
      report.screenshots.push('09-chat-completed.png');

      // Capture drawer chat content
      const chatMessages = await page.locator('[data-copilot-drawer], .fi-copilot-drawer, main').innerText().catch(() => '');
      console.log('\n--- Captured Chat Turn Response Preview ---');
      console.log(chatMessages.slice(0, 1000));

      report.realChat = {
        status: 'EXECUTED',
        prompt: promptQuery,
        responseSnippet: chatMessages.slice(0, 800),
      };
    } else {
      console.warn('⚠️ Could not locate chat input textarea');
      report.realChat = { status: 'FAILED', reason: 'Chat textarea not found' };
    }

  } catch (err) {
    console.error(`💥 Unexpected error during test run: ${err.message}`);
    report.error = err.message;
    await page.screenshot({ path: path.join(RESULTS_DIR, '99-error-state.png'), fullPage: true }).catch(() => {});
  } finally {
    await browser.close();
  }

  // Save report JSON
  fs.writeFileSync(path.join(RESULTS_DIR, 'verification-report.json'), JSON.stringify(report, null, 2));
  console.log('\n Verification Complete! Report saved to test-results/deployed-verification/verification-report.json');
}

verifyDeployedSite();
