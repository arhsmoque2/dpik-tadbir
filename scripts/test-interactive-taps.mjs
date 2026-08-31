import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const TARGET_URL = 'https://dpik-tadbir-gmnvf7efyq-as.a.run.app/admin/executive-assistant';
const ARTIFACTS_DIR = 'C:/Users/Abdul Rahman Hilmi/.gemini/antigravity-cli/brain/f2062337-bc96-4b31-a836-ada3aabe2253';
const SCREENSHOTS_DIR = path.join(ARTIFACTS_DIR, 'screenshots/interactive-taps');

if (!fs.existsSync(SCREENSHOTS_DIR)) {
  fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

async function testInteractiveTaps() {
  console.log('===============================================================');
  console.log('  🎭 [DPIK TADBIR] Live Tap & Interaction Verification');
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

  page.on('console', (msg) => console.log(`  [Console ${msg.type()}] ${msg.text()}`));
  page.on('pageerror', (err) => console.error(`  ❌ [Page Error] ${err.message}`));

  try {
    await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 45000 });

    if (page.url().includes('/admin/login')) {
      console.log('  Authenticating...');
      await page.locator('input[type="email"], input#data\\.email').first().fill('admin@dpik.com.my');
      await page.locator('input[type="password"], input#data\\.password').first().fill('password');
      await page.locator('button[type="submit"], button.fi-btn').first().click();
      await page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 25000 });
      await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 30000 });
    }

    console.log('\n--- 1. Before Tapping Anything ---');
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '01-before-any-tap.png'), fullPage: true });

    // Tap Card 1: Morning Delta Briefing
    console.log('\n--- 2. Tapping Card 1: "Morning Delta Briefing" ---');
    const card1 = page.locator('div:has-text("Morning Delta Briefing")').last();
    console.log('  Clicking Morning Delta Briefing preset card...');
    await card1.click();

    await page.waitForTimeout(3000);
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '02-after-tapping-card1.png'), fullPage: true });

    const isDrawerOpen = await page.locator('[data-copilot-drawer]').first().isVisible().catch(() => false);
    console.log(`  Drawer visible after card click: ${isDrawerOpen}`);

    // Tap Topbar AI Copilot button
    console.log('\n--- 3. Tapping Topbar "AI Copilot ⌘J" Button ---');
    const topbarBtn = page.locator('[data-copilot-trigger]').first();
    await topbarBtn.click();
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '03-after-tapping-topbar.png'), fullPage: true });

    // Tap Top-3 Model Swapper button inside drawer
    console.log('\n--- 4. Tapping Model Swapper Dropdown Button ---');
    const swapperBtn = page.locator('button[title*="Model Swapper"]').first();
    if (await swapperBtn.isVisible({ timeout: 2000 })) {
      await swapperBtn.click();
      await page.waitForTimeout(1500);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '04-after-tapping-swapper.png'), fullPage: true });

      // Tap Slot 2: DeepSeek R1
      console.log('  Tapping Slot 2 (OpenRouter DeepSeek R1)...');
      const slot2Btn = page.locator('button:has-text("DeepSeek R1")').first();
      if (await slot2Btn.isVisible({ timeout: 2000 })) {
        await slot2Btn.click();
        await page.waitForTimeout(1500);
        await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '05-after-selecting-slot2.png'), fullPage: true });
      }
    }

    // Tap preset button in ribbon: Tender Review Brief
    console.log('\n--- 5. Tapping Preset Ribbon Button: "Tender Review Brief" ---');
    const presetBtn = page.locator('button:has-text("Tender Review Brief")').first();
    if (await presetBtn.isVisible({ timeout: 2000 })) {
      await presetBtn.click();
      await page.waitForTimeout(4000);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '06-after-tapping-preset.png'), fullPage: true });
    }

    // Type in textarea and tap Send
    console.log('\n--- 6. Typing into Chat Textarea & Tapping Send ---');
    const textarea = page.locator('[data-copilot-drawer] textarea, textarea').first();
    if (await textarea.isVisible({ timeout: 2000 })) {
      await textarea.fill('Sila beri status projek PC-2023-011.');
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '07-after-typing-prompt.png') });

      const sendBtn = page.locator('button[type="submit"]:has-text("Send"), button:has-text("Send")').first();
      await sendBtn.click();
      await page.waitForTimeout(5000);
      await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '08-after-tapping-send.png'), fullPage: true });
    }

    console.log('\n✅ All interactive tap tests finished successfully!');
  } catch (err) {
    console.error(`💥 Error in tap testing: ${err.message}`);
    await page.screenshot({ path: path.join(SCREENSHOTS_DIR, '99-error.png') }).catch(() => {});
  } finally {
    await browser.close();
  }
}

testInteractiveTaps();
