import { test as setup, expect } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('authenticate as admin executive', async ({ page }) => {
  await page.goto('/admin');
  await page.waitForLoadState('domcontentloaded');

  const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email').first();
  if (await emailField.isVisible({ timeout: 3000 }).catch(() => false)) {
    const passwordField = page.locator('input[type="password"], input[name*="password"], input#data\\.password').first();
    const submitBtn = page.locator('button[type="submit"], button.fi-btn, button:has-text("Sign in"), button:has-text("Log in")').first();

    await emailField.fill('admin@dpik.com.my');
    await passwordField.fill('password');
    await submitBtn.click();

    // Wait until navigated away from login page into Filament admin portal
    await page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 15000 });
  }

  await expect(page).not.toHaveURL(/.*admin\/login/);

  // Save authenticated state to reusable storage state JSON
  await page.context().storageState({ path: authFile });
});
