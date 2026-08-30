import { test, expect } from '@playwright/test';

test.describe('E2E Journey 2: Project Register & Responsive Table Toolbar QA', () => {
  test('renders project register table with seeded records and responsive layout', async ({ page }) => {
    await page.goto('/admin/project-registers');
    await page.waitForLoadState('domcontentloaded');

    // Confirm authenticated session remained active (no redirect to login)
    await expect(page).toHaveURL(/.*admin\/project-registers/);

    // Verify table structure is rendered
    const tableElement = page.locator('.fi-ta, .fi-ta-table, [data-filament-table], table');
    await expect(tableElement.first()).toBeVisible({ timeout: 15000 });

    // Assert seeded record exists in the table view
    const seededRecord = page.locator('text=PC-2023-011, text="Jambatan Sungai Udang"');
    await expect(seededRecord.first()).toBeVisible({ timeout: 10000 });

    // Verify search input & filter action buttons
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"]');
    if (await searchInput.count() > 0) {
      await expect(searchInput.first()).toBeEnabled();
    }

    // Verify toolbar actions do not overflow viewport on mobile screens
    const viewport = page.viewportSize();
    if (viewport && viewport.width < 768) {
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 5);
    }
  });
});
