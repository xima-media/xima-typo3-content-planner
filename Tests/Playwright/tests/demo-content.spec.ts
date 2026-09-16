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
  // prefixed by its parent's. Each lookup runs in its own search() rather
  // than relying on the tree being cleared/expanded first: whether a
  // collapsed root actually renders its children on expand is lazy,
  // unasserted UI behavior, while search() is the same filtered-DOM
  // mechanism already exercised (and proven) by the loop above.
  await pageTree.search(DEMO_ROOT_PAGE_TITLE);
  const root = pageTree.node(DEMO_ROOT_PAGE_TITLE);
  await expect(root).toBeVisible();

  const rootTreeId = await root.getAttribute('data-tree-id');
  if (null === rootTreeId) {
    throw new Error('The demo root node has no data-tree-id.');
  }

  for (const title of [DEMO_STATUS_PAGE_TITLE, DEMO_DRAFT_PAGE_TITLE]) {
    await pageTree.search(title);
    const child = pageTree.node(title);
    await expect(child).toBeVisible();
    const childTreeId = await child.getAttribute('data-tree-id');
    if (null === childTreeId) {
      throw new Error(`The demo child node "${title}" has no data-tree-id.`);
    }
    expect(childTreeId.startsWith(`${rootTreeId}_`)).toBe(true);
  }
});
