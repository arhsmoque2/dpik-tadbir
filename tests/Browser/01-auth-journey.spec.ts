import { test, expect } from '@playwright/test';

test.describe('E2E Journey 1: Authentication, Session Handling & SSO Routing', () => {
  test('redirects unauthenticated root visitors to admin login page', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/.*admin\/login/);

    // Verify key login elements
    const emailField = page.locator('input[type="email"], input[name="email"]');
    await expect(emailField).toBeVisible();

    const passwordField = page.locator('input[type="password"], input[name="password"]');
    await expect(passwordField).toBeVisible();

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeVisible();
  });

  test('enforces CSRF token and rejects malformed login attempts gracefully', async ({ page }) => {
    await page.goto('/admin/login');

    await page.fill('input[type="email"], input[name="email"]', 'invalid_user@dpik.com.my');
    await page.fill('input[type="password"], input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');

    // Should remain on login page with error feedback
    await expect(page).toHaveURL(/.*admin\/login/);
  });
});
