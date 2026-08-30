import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E & Visual/Accessibility Quality Gate Config
 * Governed by ADR-015 and ADR-016.
 */
export default defineConfig({
  testDir: './tests/Browser',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    // Global authentication setup
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },
    // Authenticated desktop Chromium project
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
      testIgnore: /.*\.setup\.ts/,
    },
    // Authenticated mobile Chrome project
    {
      name: 'mobile-chrome',
      use: {
        ...devices['Pixel 7'],
        storageState: 'playwright/.auth/user.json',
      },
      dependencies: ['setup'],
      testIgnore: /.*\.setup\.ts/,
    },
  ],
  webServer: process.env.CI_URL
    ? undefined
    : {
        command: process.env.CI
          ? 'php artisan serve --env=testing --port=8000'
          : 'php artisan serve --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 120 * 1000,
      },
});
