import { test, expect } from '@playwright/test';
import { assertDialogHasReachableClose, assertIconControlsHaveAriaLabel, assertNoRawBackendErrors } from './support/hygiene';

/**
 * Journey 5: Structural navigation hygiene (Tier 0 — baseline-free).
 *
 * Regression coverage for a real bug found on the deployed instance
 * (2026-09-02, see docs/handoffs — captured .mht of the live admin panel):
 * on mobile, the AI Copilot drawer opens as a full-viewport overlay above
 * the floating bottom nav, and its only close affordance was an icon-only
 * button whose accessible name came solely from a `title` attribute — no
 * on-screen cue on a touch device. These checks assert the underlying
 * contract (a real, accessibly-named way back to primary navigation always
 * exists) rather than any specific pixel layout, so a legitimate visual
 * redesign can never trip them — only an actual navigation dead-end can.
 */

const BOTTOM_NAV = '[aria-label="Floating Primary Navigation"]';
const DRAWER = '[data-copilot-drawer]';

test.describe('Journey 5: Navigation Hygiene (Tier 0)', () => {
    test('AI Copilot drawer exposes a reachable, accessibly-named close control', async ({ page }) => {
        await page.goto('/admin');
        await page.waitForLoadState('domcontentloaded');

        await page.locator('[data-copilot-trigger]').first().click();
        await assertDialogHasReachableClose(page, DRAWER, BOTTOM_NAV);
    });

    test('AI Copilot drawer does not strand mobile users away from primary navigation', async ({ page }, testInfo) => {
        test.skip(!testInfo.project.name.includes('mobile'), 'Mobile-viewport-specific: the drawer is only full-width below the md breakpoint.');

        await page.goto('/admin');
        await page.waitForLoadState('domcontentloaded');

        await page.locator('[data-copilot-center-fab]').first().click();
        const drawer = page.locator(DRAWER);
        await expect(drawer).toBeVisible();

        // Whatever the drawer's width strategy on this viewport, it must
        // never make the nav's only other exit unreachable: a real click
        // on the drawer's own close control must succeed.
        const closeButton = drawer.getByRole('button', { name: /close/i });
        await expect(closeButton.first()).toBeVisible({ timeout: 5000 });
        await closeButton.first().click();
        await expect(drawer).toBeHidden();
        await expect(page.locator(BOTTOM_NAV)).toBeVisible();
    });

    test('bottom nav, center FAB, and drawer header icon controls all have real accessible names', async ({ page }) => {
        await page.goto('/admin');
        await page.waitForLoadState('domcontentloaded');
        await assertIconControlsHaveAriaLabel(page, BOTTOM_NAV);

        await page.locator('[data-copilot-trigger]').first().click();
        await expect(page.locator(DRAWER)).toBeVisible();
        await assertIconControlsHaveAriaLabel(page, DRAWER);
    });

    test('default bottom nav renders exactly the four documented default slots plus the fixed Copilot FAB', async ({ page }) => {
        // Source of truth: App\Models\User::getBottomNavSlots() default array
        // (app/Models/User.php) — not docs/ui-spec/navigation-tree.json's
        // `mobileNavigation.defaultVisible`, which has drifted from what the
        // app actually ships (see review notes). Update this list only in
        // lockstep with a deliberate change to that PHP default.
        await page.goto('/admin');
        await page.waitForLoadState('domcontentloaded');

        const nav = page.locator(BOTTOM_NAV);
        await expect(nav).toBeVisible();

        const labels = await nav.locator('a[title]').evaluateAll((els) => els.map((el) => el.getAttribute('title')));
        expect(labels).toEqual(['Copilot', 'Bundles', 'Notes', 'Settings']);

        await expect(nav.locator('[data-copilot-center-fab]')).toBeVisible();
    });

    test('the copilot drawer never renders a raw backend/process error to the user', async ({ page }) => {
        await page.goto('/admin');
        await page.waitForLoadState('domcontentloaded');

        await page.locator('[data-copilot-trigger]').first().click();
        const drawer = page.locator(DRAWER);
        await expect(drawer).toBeVisible();

        // This asserts the always-true invariant on whatever the drawer
        // currently renders (presets ribbon, empty state, or prior transcript)
        // rather than forcing a live model call — the equivalent deliberate
        // fault-injection scenario (a missing `uv` binary) is covered
        // deterministically in tests/Feature/Mcp/OutlookMcpBridgeTest.php,
        // since Playwright's own CI run boots the app in APP_ENV=testing,
        // where the Outlook bridge is always mocked and this failure path
        // is unreachable from the browser.
        await assertNoRawBackendErrors(page, '[data-copilot-drawer]');
    });
});
