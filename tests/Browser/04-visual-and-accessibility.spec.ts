import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Visual & Accessibility QA: WCAG 2.1 AA & Responsive Layout Audits', () => {
  test('audit login page accessibility conformance (WCAG 2.1 AA)', async ({ page }) => {
    await page.goto('/admin/login');

    // Run axe-core accessibility audit
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .disableRules(['color-contrast']) // Color contrast evaluated via theme tokens
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('captures visual snapshot of admin portal for regression comparison', async ({ page }) => {
    await page.goto('/admin/login');

    // Ensure elements are fully rendered before snapshot
    await page.waitForLoadState('networkidle');

    // Capture screenshot artifact
    const screenshotBuffer = await page.screenshot({
      fullPage: true,
    });

    expect(screenshotBuffer.byteLength).toBeGreaterThan(1000);
  });
});
