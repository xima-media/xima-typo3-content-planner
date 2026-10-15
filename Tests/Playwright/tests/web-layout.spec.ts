import { test, expect } from '@playwright/test';
import { WebLayoutPage } from '../support/web-layout.page';
import {
  DEMO_ASSIGNEE_USERNAME,
  DEMO_STATUS_PAGE_TITLE,
  DEMO_STATUS_TITLE_FOR_STATUS_PAGE,
} from '../support/demo-content';

/*
 * Covers #313's Web > Page status surface: opening a page carrying a status shows the Content
 * Planner button group in the doc header (default `chip` display mode), added through
 * `ModifyButtonBarEvent` by `ChipTrioButtonBuilder`. The banner of the `banner` mode is not
 * covered here.
 */

test('opening a page with a status shows the status header in Web > Page', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await expect(webLayout.statusButton(DEMO_STATUS_TITLE_FOR_STATUS_PAGE)).toBeVisible();
  await expect(webLayout.assigneeButton()).toHaveAttribute('data-table', 'pages');

  // The demo fixture also carries an assignee and three comments, one of them a reply (see
  // support/demo-content.ts; the count includes replies) - asserting on both proves the
  // header renders real record state, not just a static shell.
  await expect(webLayout.assigneeButton()).toContainText(DEMO_ASSIGNEE_USERNAME);
  await expect(webLayout.commentsButton()).toContainText('3');
});
