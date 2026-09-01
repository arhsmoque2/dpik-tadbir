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
    // No exclusions: the floating bottom nav only renders when
    // `auth()->check()` is true (see the `BODY_END` render hook in
    // AdminPanelProvider), so it never appears on this unauthenticated
    // login page — an earlier exclusion here was a workaround for a
    // different bug (this test was accidentally scanning the authenticated
    // dashboard, not the login page — see AUTH_ENABLED in ci.yml's Gate 4
    // bootstrap) rather than a real axe false positive on this surface.
    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('login page visual regression against the approved baseline', async ({ page }) => {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');

    // Diffs the live render against a committed baseline PNG under
    // tests/Browser/04-visual-and-accessibility.spec.ts-snapshots/ (one per
    // project — chromium and mobile-chrome each get their own file).
    // That baseline IS "what's approved": it only changes when a human (or
    // an agent acting on human direction) reviews an intentional UI change
    // and re-generates it — `npx playwright test 04-visual-and-accessibility
    // --update-snapshots`, committed in the same PR as the change that
    // caused the diff. Gate 4 then fails only on *unapproved* pixel drift
    // beyond a 5% tolerance (`maxDiffPixelRatio`), not on every legitimate
    // redesign — that's the incremental-baseline model: the gate enforces
    // "nothing changed since the last approval," not a fixed golden image.
    await expect(page).toHaveScreenshot('admin-login.png', {
      fullPage: true,
      animations: 'disabled',
      maxDiffPixelRatio: 0.05,
    });
  });
});
