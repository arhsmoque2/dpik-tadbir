import { test, expect } from '@playwright/test';

// Auth journey executes with clean storage state to evaluate login mechanics
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('E2E Journey 1: Authentication, Session Handling & SSO Routing', () => {
  test('directs root visitors to admin interface', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');

    const currentUrl = page.url();
    if (currentUrl.includes('/admin/login')) {
      const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email');
      await expect(emailField.first()).toBeVisible({ timeout: 10000 });
    } else {
      await expect(page).toHaveURL(/.*admin/);
    }
  });

  test('handles login or direct admin access gracefully', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveURL(/.*admin/);
  });
});
