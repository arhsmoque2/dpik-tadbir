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

    // Verify the seeded preset itself is rendered in the ribbon — not just the
    // "Presets:" label. This was weakened to the label-only check in PR #17,
    // which papered over ExecutivePreset being seeded scoped to a specific
    // admin user_id that AutoLoginBypassMiddleware doesn't necessarily log in
    // as, silently rendering an empty ribbon for the real logged-in executive.
    // Fixed at the source (DatabaseSeeder now seeds this preset as a
    // system-wide default, user_id null) rather than loosening the assertion
    // again.
    const presetBtn = drawer.getByText('Tender Review Brief');
    await expect(presetBtn.first()).toBeVisible({ timeout: 10000 });

    // Verify input textarea is available for executive instructions
    const promptInput = drawer.locator('textarea');
    await expect(promptInput.first()).toBeVisible();

    // Close drawer via Escape key
    await page.keyboard.press('Escape');
  });
});
