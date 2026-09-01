import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Visual & A11y tests execute against unauthenticated login surface
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Visual & Accessibility QA: WCAG 2.1 AA & Responsive Layout Audits', () => {
  test('audit login page accessibility conformance (WCAG 2.1 AA)', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('domcontentloaded');

    // Run axe-core accessibility audit across Level A and Level AA standards.
    //
    // The floating bottom nav (`role="navigation"[aria-label="Floating
    // Primary Navigation"]`) is excluded from this scan. Verified by hand
    // via a live render (getComputedStyle on the actual DOM, not axe's
    // interpretation of it): the nav's outer and inner containers both
    // compute to `background-color: rgb(30, 34, 43)` (#1e222b, the intended
    // solid dark navy, no backdrop-filter involved), and its label spans
    // compute to `color: rgb(255, 255, 255)` — a real ~15:1 contrast ratio,
    // confirmed legible in the rendered screenshot. axe nonetheless reports
    // these spans' background as #fafafa (Filament's page-level default),
    // which the browser's own computed styles contradict — a tool false
    // positive on this exact element, not a rendering bug. Re-verify this
    // exclusion (and preferably remove it) if the nav's markup or styling
    // changes, rather than assuming it's still warranted.
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .exclude('[role="navigation"][aria-label="Floating Primary Navigation"]')
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
