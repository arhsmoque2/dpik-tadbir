import { test, expect } from '@playwright/test';

test.describe('E2E Journey 3: Livewire AI Copilot Drawer & Action Card Ledger', () => {
  test('validates copilot trigger presence, drawer expansion, and preset ribbon', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('domcontentloaded');

    // Confirm authenticated session (no redirect to login)
    await expect(page).toHaveURL(/.*admin/);

    // Locate copilot topbar trigger button
    const copilotTrigger = page.locator('[data-copilot-trigger]');
    await expect(copilotTrigger.first()).toBeVisible({ timeout: 15000 });

    // Open copilot drawer
    await copilotTrigger.first().click();

    // Verify slide-over drawer panel opens and is visible
    const drawer = page.locator('[data-copilot-drawer]');
    await expect(drawer.first()).toBeVisible({ timeout: 10000 });

    // Verify presets ribbon is rendered
    const presetsRibbon = drawer.getByText('Presets:');
    await expect(presetsRibbon.first()).toBeVisible({ timeout: 10000 });

    // Verify input textarea is available for executive instructions
    const promptInput = drawer.locator('textarea');
    await expect(promptInput.first()).toBeVisible();

    // Close drawer via Escape key
    await page.keyboard.press('Escape');
  });
});
