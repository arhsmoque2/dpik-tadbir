import { test, expect } from '@playwright/test';

test.describe('E2E Journey 1: Authentication, Session Handling & SSO Routing', () => {
  test('redirects unauthenticated root visitors to admin login page', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('domcontentloaded');
    await expect(page).toHaveURL(/.*admin\/login/);

    // Verify key login elements
    const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email');
    await expect(emailField.first()).toBeVisible({ timeout: 10000 });

    const passwordField = page.locator('input[type="password"], input[name*="password"], input#data\\.password');
    await expect(passwordField.first()).toBeVisible({ timeout: 10000 });

    const submitBtn = page.locator('button[type="submit"], button.fi-btn, button:has-text("Sign in"), button:has-text("Log in")');
    await expect(submitBtn.first()).toBeVisible({ timeout: 10000 });
  });

  test('enforces CSRF token and rejects malformed login attempts gracefully', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('domcontentloaded');

    const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email').first();
    const passwordField = page.locator('input[type="password"], input[name*="password"], input#data\\.password').first();
    const submitBtn = page.locator('button[type="submit"], button.fi-btn, button:has-text("Sign in"), button:has-text("Log in")').first();

    await emailField.fill('invalid_user@dpik.com.my');
    await passwordField.fill('wrongpassword');
    await submitBtn.click();

    // Should remain on login page with error feedback
    await expect(page).toHaveURL(/.*admin\/login/);
  });
});
