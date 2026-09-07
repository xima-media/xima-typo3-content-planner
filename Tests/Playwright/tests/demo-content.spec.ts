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

  // The loop above only proves the three titles exist somewhere in the tree -
  // it would still pass if the seeder had scattered them outside the demo
  // root. Assert the actual hierarchy: TYPO3's page-tree renderer stamps
  // every treeitem with `data-tree-id`, built from its full ancestor path
  // (`<parent-data-tree-id>_<own-id>`), so a child's value is provably
  // prefixed by its parent's. Verified against the tree.ts shipped in this
  // project's pinned typo3/cms-backend (v13.4.33): the search filter above
  // removes non-matching nodes from the DOM entirely, so the filter has to
  // be cleared and the root expanded before the children are reachable.
  await pageTree.clear();

  const root = pageTree.node(DEMO_ROOT_PAGE_TITLE);
  await expect(root).toBeVisible();

  if ('false' === (await root.getAttribute('aria-expanded'))) {
    await root.locator('.node-toggle').click();
  }
  await expect(root).toHaveAttribute('aria-expanded', 'true');

  const rootTreeId = await root.getAttribute('data-tree-id');
  if (null === rootTreeId) {
    throw new Error('The demo root node has no data-tree-id.');
  }

  for (const title of [DEMO_STATUS_PAGE_TITLE, DEMO_DRAFT_PAGE_TITLE]) {
    const child = pageTree.node(title);
    await expect(child).toBeVisible();
    await expect(child).toHaveAttribute('data-tree-id', new RegExp(`^${rootTreeId}_`));
  }
});
