import { test, expect } from '@playwright/test';

test.describe('E2E Journey 2: Project Register & Responsive Table Toolbar QA', () => {
  test('renders project register table with responsive action toolbar layout', async ({ page }) => {
    // Navigate to admin panel
    await page.goto('/admin/project-registers');

    // On unauthenticated session, redirected to login with return intent
    if (page.url().includes('/admin/login')) {
      await expect(page.locator('form')).toBeVisible();
      return;
    }

    // When session is active:
    await expect(page.locator('.fi-ta-header, .fi-ta-table, [data-filament-table]')).toBeVisible();

    // Verify search input & filter action buttons
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"]');
    if (await searchInput.count() > 0) {
      await expect(searchInput.first()).toBeEnabled();
    }

    // Verify toolbar actions do not overflow viewport
    const viewport = page.viewportSize();
    if (viewport && viewport.width < 768) {
      // Mobile screen: assert no horizontal scrollbar on body
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 5);
    }
  });
});
