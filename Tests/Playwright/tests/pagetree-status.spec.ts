import { test, expect } from '@playwright/test';
import { BackendPage, PageTreePage } from '@konradmichalik/ptu';
import { StatusMenuPage } from '../support/status-menu.page';
import {
  DEMO_DRAFT_PAGE_TITLE,
  DEMO_STATUS_PAGE_TITLE,
  DEMO_STATUS_TITLE_FOR_STATUS_PAGE,
} from '../support/demo-content';

/*
 * Covers #313's page-tree status indicator: the colour chip
 * `AfterPageTreeItemsPreparedListener` attaches to every tree node via core's tree
 * Label DTO (see Documentation/DeveloperCorner/PageTreeIntegration.rst for why the
 * TreeController XCLASS exists to make status/comment columns available to it), and
 * StatusItemProvider's page-tree context menu, whose status change is applied via
 * AJAX and a targeted tree refresh rather than a full reload (context-menu-actions.js).
 */

test('a page carrying a status shows a coloured chip on its page-tree node', async ({ page }) => {
  await new BackendPage(page).openModule('web/layout');
  const pageTree = new PageTreePage(page);

  await pageTree.search(DEMO_STATUS_PAGE_TITLE);
  const node = pageTree.node(DEMO_STATUS_PAGE_TITLE);
  await node.waitFor({ state: 'visible' });

  // The chip carries no text of its own (core renders an empty `.node-label` span with
  // just a background colour) - the status title lives in the node's native `title`
  // tooltip instead, so that is what proves the *correct* status landed on the node,
  // not merely that the extension attached some label.
  await expect(node).toHaveAttribute('title', new RegExp(DEMO_STATUS_TITLE_FOR_STATUS_PAGE));

  const chipColor = await node.locator('.node-label').evaluate(
    (el) => getComputedStyle(el).backgroundColor,
  );
  expect(chipColor).not.toBe('rgba(0, 0, 0, 0)');
});

test('assigning a status via the page-tree context menu updates the node without a full reload', async ({ page }) => {
  await new BackendPage(page).openModule('web/layout');
  const pageTree = new PageTreePage(page);

  // Ground truth for the target colour: read it off the page that already carries
  // this status, rather than hardcoding a colour value the extension's config could
  // change independently of this test (see project memory: assert against reality).
  await pageTree.search(DEMO_STATUS_PAGE_TITLE);
  const referenceColor = await pageTree
    .node(DEMO_STATUS_PAGE_TITLE)
    .locator('.node-label')
    .evaluate((el) => getComputedStyle(el).backgroundColor);

  await pageTree.clear();
  await pageTree.search(DEMO_DRAFT_PAGE_TITLE);
  const node = pageTree.node(DEMO_DRAFT_PAGE_TITLE);
  await node.waitFor({ state: 'visible' });

  const urlBeforeChange = page.url();

  const statusMenu = new StatusMenuPage(page);
  await statusMenu.openFor(node);
  await statusMenu.chooseStatus(DEMO_STATUS_TITLE_FOR_STATUS_PAGE);

  await statusMenu.waitForNodeLabelColor(node, referenceColor);
  await expect(node).toHaveAttribute('title', new RegExp(DEMO_STATUS_TITLE_FOR_STATUS_PAGE));

  // No navigation happened - the whole point of the targeted refresh in
  // refreshPageTreeNode() is that only the affected node's subtree is refetched.
  expect(page.url()).toBe(urlBeforeChange);
});
