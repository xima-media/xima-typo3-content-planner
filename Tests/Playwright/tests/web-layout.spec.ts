import { test, expect } from '@playwright/test';
import { WebLayoutPage } from '../support/web-layout.page';
import {
  DEMO_ASSIGNEE_USERNAME,
  DEMO_STATUS_PAGE_TITLE,
  DEMO_STATUS_TITLE_FOR_STATUS_PAGE,
} from '../support/demo-content';

/*
 * Covers #313's Web > Page status header: opening a page carrying a status shows the
 * banner `DrawBackendHeaderListener` / `InfoGenerator::generateStatusHeader()` add to
 * `ModifyPageLayoutContentEvent` (`HeaderMode::WEB_LAYOUT`), rendered from
 * Resources/Private/Templates/Backend/Header/HeaderInfo.html.
 *
 * IMPORTANT: this asserts against the CURRENT (pre-#324) banner UI on this branch's
 * ancestry - a single `.content-planner-header` bar with status text, an assignee
 * button and a comments button. #324 ("unified status display") replaces this with a
 * chip-trio header behind a `headerDisplayMode` flag defaulting to `chip`, but that
 * work lives on the CP-architecture epic branch, not in this e2e stack. This spec will
 * need revisiting (selectors and/or assertions) once the two stacks are merged.
 */

test('opening a page with a status shows the status header in Web > Page', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await expect(webLayout.header()).toBeVisible();
  await expect(webLayout.header()).toHaveAttribute('data-table', 'pages');
  await expect(webLayout.statusBody()).toContainText(DEMO_STATUS_TITLE_FOR_STATUS_PAGE);

  // The demo fixture also carries an assignee and one comment (see support/demo-content.ts) -
  // asserting on both proves the header renders real record state, not just a static shell.
  await expect(webLayout.header().locator('[data-content-planner-assignees]')).toContainText(
    DEMO_ASSIGNEE_USERNAME,
  );
  await expect(webLayout.commentsBadge()).toHaveText('1');
});
