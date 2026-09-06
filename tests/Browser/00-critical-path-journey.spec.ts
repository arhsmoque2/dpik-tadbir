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

        // Force a fresh chat session rather than reusing whatever session
        // already exists for this seeded user. AiCopilotDrawer::
        // ensureActiveSession() reuses the latest existing session — without
        // this, this test's own history accumulates across repeated runs
        // (and across the chromium/mobile-chrome projects sharing the same
        // seeded login within one CI run), making later assertions
        // (a specific reply's text, a specific action card) increasingly
        // likely to match multiple stale elements instead of just this
        // run's. Confirmed via local repro: without this, the 2nd+ run
        // against the same DB hits a strict-mode violation on step 3's
        // assertion. force: true for the same mobile-viewport scroll-race
        // reason as the Send button below.
        const drawer = page.locator('[data-copilot-drawer]');
        await drawer.getByRole('button', { name: 'New session' }).click({ force: true });

        // "New session" triggers an async Livewire request that morphs the
        // drawer's DOM (clearing the message stream). Proceeding immediately
        // races that request: Step 3's fill()+click() can land on the
        // pre-reset component an instant before Livewire replaces it,
        // silently dropping the input. Confirmed via a local trace — the
        // send appeared to succeed but the message never actually reached
        // the (new) component. Wait for the empty-state placeholder that
        // only renders once the reset has actually landed.
        await expect(drawer.getByText('Executive Workspace Ready')).toBeVisible({ timeout: 10000 });
    });

    await test.step('Step 3 — UI-related inquiry: ask a general question and get a real assistant reply', async () => {
        const drawer = page.locator('[data-copilot-drawer]');
        const promptInput = drawer.locator('textarea');
        await expect(promptInput.first()).toBeVisible();

        // Deliberately avoids "inbox"/"delta"/"draft" — LlmGatewayService's
        // mockCompletion() keys off those literal substrings for its other
        // branches (checked before its plain default reply), and this step
        // asserts on that default reply specifically.
        await promptInput.first().fill('How do I use the AI copilot here?');
        // Driving the form's real submit control rather than the Cmd/Ctrl+Enter
        // Alpine shortcut (@keydown.ctrl.enter on the textarea) — that
        // shortcut isn't reliably observed by Playwright's synthesized
        // keyboard events in headless Chromium, where this "Send" button is.
        //
        // force: true — confirmed via a local trace (not just guessed) that
        // this button is always the correct, visible, enabled target; the
        // plain click intermittently fails its hit-test on mobile-chrome
        // because focusing the textarea just above triggers the emulated
        // device's native "scroll input into view" behavior, which races
        // Playwright's own scroll-then-click and lands the hit-test on
        // whatever's mid-scroll under that pixel (the drawer header one
        // retry, the message stream the next) — scroll noise, not a real
        // occlusion. If this starts failing for a different reason, don't
        // just re-add force blindly: pull the trace first.
        await drawer.getByRole('button', { name: /^send$/i }).click({ force: true });

        // The mock's default branch (LlmGatewayService::mockCompletion) —
        // no tool call, just a direct reply. Confirms the round trip works
        // before the flow moves on to a tool-driving prompt.
        await expect(drawer.getByText('DPIK Tadbir Copilot ready', { exact: false })).toBeVisible({ timeout: 15000 });
    });

    await test.step('Step 4 — send a relevant email: prompt drafts a reply, action card is staged', async () => {
        const drawer = page.locator('[data-copilot-drawer]');
        const promptInput = drawer.locator('textarea');

        await promptInput.first().fill('Please draft a reply confirming our attendance.');
        // force: true — same mobile-viewport scroll race as Step 3's Send click.
        await drawer.getByRole('button', { name: /^send$/i }).click({ force: true });

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

    await test.step('Step 6 — leave a clean session behind for whatever runs next', async () => {
        // tools/capabilities/generate.php's --verify-browser mode runs each
        // capability's test as a separate `playwright test -g <title>`
        // subprocess, all sharing the one already-seeded DB for the whole CI
        // job — and AiCopilotDrawer::ensureActiveSession() reuses this
        // seeded user's *latest* session rather than creating one per test.
        // Files are scanned in sorted-path order (00- before 05-), so this
        // capability verifies before 05-navigation-hygiene.spec.ts's —
        // without this, this test's own populated conversation becomes the
        // "latest" session, and the next capability's separate verification
        // run inherits it, reproducing this same mobile-viewport scroll race
        // (confirmed: this is what broke 'chat.copilot-drawer-close' in CI
        // right after this test was added). Starting fresh (Step 2) isn't
        // enough on its own — leaving fresh behind is what protects whoever
        // verifies next. force: true for the same mobile-viewport scroll-race
        // reason as the other clicks in this test; then wait for the
        // empty-state placeholder so the reset has actually landed before
        // this browser context closes — a click with no confirmation
        // followed immediately by the test (and browser) ending risks the
        // async Livewire request never completing at all.
        const drawer = page.locator('[data-copilot-drawer]');
        await drawer.getByRole('button', { name: 'New session' }).click({ force: true });
        await expect(drawer.getByText('Executive Workspace Ready')).toBeVisible({ timeout: 10000 });
    });
});
