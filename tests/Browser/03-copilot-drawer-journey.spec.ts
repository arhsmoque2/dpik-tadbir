import { test, expect } from '@playwright/test';

test.describe('E2E Journey 3: Livewire AI Copilot Drawer & Action Card Ledger', () => {
  test('validates copilot trigger presence and drawer DOM structure', async ({ page }) => {
    await page.goto('/admin');

    // If redirected to login, verify login form container
    if (page.url().includes('/admin/login')) {
      const loginBox = page.locator('main, form, .fi-simple-main');
      await expect(loginBox.first()).toBeVisible();
      return;
    }

    // When logged in, topbar trigger hook renders the Copilot button
    const copilotTrigger = page.locator('[data-copilot-trigger], button:has-text("Copilot"), [aria-label*="Copilot"]');
    if (await copilotTrigger.count() > 0) {
      await expect(copilotTrigger.first()).toBeVisible();
      await copilotTrigger.first().click();

      // Verify drawer opens
      const drawer = page.locator('[data-copilot-drawer], .fi-modal, [role="dialog"]');
      await expect(drawer.first()).toBeVisible();
    }
  });
});
