import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Visual & A11y tests execute against unauthenticated login surface
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Visual & Accessibility QA: WCAG 2.1 AA & Responsive Layout Audits', () => {
  test('audit login page accessibility conformance (WCAG 2.1 AA)', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('domcontentloaded');

    // Run axe-core accessibility audit across Level A and Level AA standards
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('captures visual snapshot of admin portal for regression comparison', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Capture visual screenshot artifact for artifact upload and regression verification
    const screenshot = await page.screenshot({
      path: 'test-results/admin-login.png',
      fullPage: true,
      animations: 'disabled',
    });
    expect(screenshot.byteLength).toBeGreaterThan(10000);
  });
});
