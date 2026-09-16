import { test, expect } from '@playwright/test';
import { BackendPage } from '@konradmichalik/ptu';

/*
 * Base infrastructure smoke test for #311: proves the auth.setup.ts login
 * flow produces a valid storageState and the backend dashboard is reachable
 * afterwards. Feature-specific specs land in later issues.
 */
test('the backend dashboard loads after login', async ({ page }) => {
  await new BackendPage(page).openModule('dashboard');

  await expect(page).toHaveTitle(/TYPO3/);
  await expect(page.getByRole('navigation', { name: 'Module Menu' })).toBeVisible();
});
