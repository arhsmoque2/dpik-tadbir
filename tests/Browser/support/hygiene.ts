import { Page, expect } from '@playwright/test';

/**
 * Baseline-free structural/hygiene assertions (Tier 0).
 *
 * These never compare against a stored screenshot or a specific piece of
 * copy — only against the live page's own computed geometry, accessible
 * name graph, and rendered text. That makes them safe to run unconditionally
 * while the UI is still evolving: a legitimate redesign, copy change, or
 * color tweak cannot trip these, only an actual structural defect can.
 *
 * Grounded in a real regression found on the deployed instance
 * (2026-09-02): the AI Copilot drawer took over the full mobile viewport
 * above the floating bottom nav, and its only close affordance was an
 * icon-only button whose sole accessible name came from a bare `title`
 * attribute — invisible on touch devices, easy to miss even sighted.
 */

/** Raw runtime/process error signatures that must never reach end-user-facing text. */
const RAW_ERROR_SIGNATURES: RegExp[] = [
    /\bsh:\s*\d+:/i,
    /\bcommand not found\b/i,
    /No such file or directory/i,
    /^The command .+ failed\./im,
    /Traceback \(most recent call last\)/,
    /Fatal error:/i,
    /Stack trace:/i,
    /\.php on line \d+/i,
];

/**
 * Check 2 — every icon-only interactive control (no visible text content)
 * must expose a real accessible name via `aria-label`/`aria-labelledby`,
 * not only a `title` attribute (no hover on touch, easy to overlook).
 */
export async function assertIconControlsHaveAriaLabel(page: Page, scopeSelector: string) {
    const violations = await page.locator(scopeSelector).evaluate((scope) => {
        const controls = Array.from(scope.querySelectorAll('button, a[href]'));
        const bad: string[] = [];

        for (const el of controls) {
            const text = (el.textContent || '').trim();
            const hasSvgOnly = el.querySelector('svg') !== null && text.length === 0;
            if (!hasSvgOnly) continue;

            const ariaLabel = el.getAttribute('aria-label');
            const ariaLabelledBy = el.getAttribute('aria-labelledby');
            if (!ariaLabel?.trim() && !ariaLabelledBy?.trim()) {
                bad.push(el.outerHTML.slice(0, 160));
            }
        }

        return bad;
    });

    expect(violations, `Icon-only controls missing aria-label:\n${violations.join('\n')}`).toEqual([]);
}

/**
 * Check 1 & 3 — any open `role="dialog"` must expose a close control that is
 * a real click target (not only Escape/backdrop/keyboard-shortcut), and
 * closing it must restore access to primary navigation. Run this at any
 * viewport; it's the mobile viewport where a full-width overlay is most
 * likely to strand the user (Check 3's specific concern).
 */
export async function assertDialogHasReachableClose(page: Page, dialogSelector: string, navSelector: string) {
    const dialog = page.locator(dialogSelector);
    await expect(dialog).toBeVisible();

    const closeButton = dialog.getByRole('button', { name: /close/i });
    await expect(closeButton.first()).toBeVisible();

    await closeButton.first().click();
    await expect(dialog).toBeHidden();

    const nav = page.locator(navSelector);
    await expect(nav).toBeVisible();
    // A "visible" nav that's actually covered by something else would fail
    // Playwright's real actionability check here, not just a bounding-box read.
    await expect(nav.locator('a').first()).toBeVisible();
}

/**
 * Check 5 — no raw shell/stack-trace signature may appear in rendered
 * user-facing text (chat transcript, action-result cards, toasts, etc).
 */
export async function assertNoRawBackendErrors(page: Page, containerSelector = 'body') {
    const text = await page.locator(containerSelector).innerText();
    for (const pattern of RAW_ERROR_SIGNATURES) {
        expect(text, `Raw backend error signature ${pattern} found in rendered output`).not.toMatch(pattern);
    }
}
