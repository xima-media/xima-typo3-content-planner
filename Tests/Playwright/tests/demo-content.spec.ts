import { test, expect } from '@playwright/test';
import { BackendPage, PageTreePage } from '@konradmichalik/ptu';
import {
  DEMO_DRAFT_PAGE_TITLE,
  DEMO_ROOT_PAGE_TITLE,
  DEMO_STATUS_PAGE_TITLE,
} from '../support/demo-content';

/*
 * Proves Tests/Playwright/global-setup.ts actually re-seeded the demo page
 * tree (#312) before this suite ran, rather than merely not crashing. Later
 * issues in the e2e epic assert against status/assignee/comment details on
 * these same fixtures; this spec only covers that the tree itself exists.
 */
test('the seeded demo page tree is visible in the backend', async ({ page }) => {
  await new BackendPage(page).openModule('web/layout');
  const pageTree = new PageTreePage(page);

  for (const title of [DEMO_ROOT_PAGE_TITLE, DEMO_STATUS_PAGE_TITLE, DEMO_DRAFT_PAGE_TITLE]) {
    await pageTree.search(title);
    await expect(pageTree.node(title)).toBeVisible();
  }
});
