import { test, expect } from '@playwright/test';

/**
 * Journey 0: Critical Path — the single, chained "does the product actually
 * work end-to-end" flow, as distinct from the per-surface journeys in
 * 01-05 (which each start fresh and exist for fast bisection of one
 * feature area in isolation).
 *
 * This test walks the sequence a real executive actually performs, in
 * order, in one continuous session: sign in, land on the chat interface,
 * ask it something, and have it stage an email action for approval. It is
 * meant to grow — never fork — as new capabilities land on this literal
 * path: append a new `test.step()` after the step it now follows, wire it
 * into the same flow, and (only if the new step needs its own audit-able
 * proof) give it its own `@capability(...)`-tagged test elsewhere: the
 * capability gate (tools/capabilities/{generate,diff}.php) is what tracks
 * "declared vs. built", this spec is what tracks "declared capabilities
 * actually compose into one usable flow, in the order a user hits them."
 * See CURRENT_STATE.md / DEVTOOLS.md for how the two relate.
 *
 * Deterministic and hermetic: the AI step relies on
 * App\Services\Ai\LlmGatewayService::mockCompletion() — the same
 * env('APP_ENV')==='testing' fallback the Feature test suite uses (see
 * tests/Feature/Livewire/AiCopilotDrawerTest.php) — which recognizes the
 * literal word "draft" in the prompt and returns a scripted
 * `propose_action_card` tool call. No live LLM provider call, no network
 * dependency, no flake budget.
 */

test.use({ storageState: { cookies: [], origins: [] } });

// @capability(journey.critical-path-login-to-mail-dispatch)
test('critical path: login → chat interface → UI inquiry → email action staged & approved', async ({ page }) => {
    await test.step('Step 1 — login: reach the chat-capable admin interface from a signed-out session', async () => {
        await page.goto('/admin');
        await page.waitForLoadState('domcontentloaded');

        const emailField = page.locator('input[type="email"], input[name*="email"], input#data\\.email').first();
        if (await emailField.isVisible({ timeout: 3000 }).catch(() => false)) {
            const passwordField = page.locator('input[type="password"], input[name*="password"], input#data\\.password').first();
            const submitBtn = page.locator('button[type="submit"], button.fi-btn, button:has-text("Sign in"), button:has-text("Log in")').first();

            await emailField.fill('admin@dpik.com.my');
            await passwordField.fill('password');
            await submitBtn.click();
            await page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 15000 });
        }

        await expect(page).not.toHaveURL(/.*admin\/login/);
    });

    await test.step('Step 2 — chat interface: open the AI Copilot drawer', async () => {
        const copilotTrigger = page.locator('[data-copilot-trigger]');
        await expect(copilotTrigger.first()).toBeVisible({ timeout: 15000 });
        await copilotTrigger.first().click();

        await expect(page.locator('[data-copilot-drawer]')).toBeVisible({ timeout: 10000 });
    });

    await test.step('Step 3 — UI-related inquiry: ask a general question and get a real assistant reply', async () => {
        const drawer = page.locator('[data-copilot-drawer]');
        const promptInput = drawer.locator('textarea');
        await expect(promptInput.first()).toBeVisible();

        await promptInput.first().fill('How do I use the executive inbox?');
        // Driving the form's real submit control rather than the Cmd/Ctrl+Enter
        // Alpine shortcut (@keydown.ctrl.enter on the textarea) — that
        // shortcut isn't reliably observed by Playwright's synthesized
        // keyboard events in headless Chromium, where this "Send" button is.
        await drawer.getByRole('button', { name: /^send$/i }).click();

        // The mock's default branch (LlmGatewayService::mockCompletion) —
        // no tool call, just a direct reply. Confirms the round trip works
        // before the flow moves on to a tool-driving prompt.
        await expect(drawer.getByText('DPIK Tadbir Copilot ready', { exact: false })).toBeVisible({ timeout: 15000 });
    });

    await test.step('Step 4 — send a relevant email: prompt drafts a reply, action card is staged', async () => {
        const drawer = page.locator('[data-copilot-drawer]');
        const promptInput = drawer.locator('textarea');

        await promptInput.first().fill('Please draft a reply confirming our attendance.');
        await drawer.getByRole('button', { name: /^send$/i }).click();

        // Mocked as a propose_action_card tool call (LlmGatewayService
        // mockCompletion's 'draft' branch) — deterministic, no live AI call.
        const dispatchButton = drawer.getByRole('button', { name: /approve & dispatch/i });
        await expect(dispatchButton).toBeVisible({ timeout: 15000 });
        await expect(drawer.getByText('Draft Reply', { exact: false })).toBeVisible();
    });

    await test.step('Step 5 — approve the staged email action', async () => {
        const drawer = page.locator('[data-copilot-drawer]');
        const dispatchButton = drawer.getByRole('button', { name: /approve & dispatch/i });

        await dispatchButton.click();

        // The card clears once the (mocked) tool result comes back and the
        // turn resolves — asserts the approval actually round-tripped
        // rather than just that the button was clickable.
        await expect(dispatchButton).toBeHidden({ timeout: 15000 });
    });
});
