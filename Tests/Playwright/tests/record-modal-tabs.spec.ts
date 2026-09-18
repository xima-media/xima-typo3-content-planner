import { test, expect } from '@playwright/test';
import { WebLayoutPage } from '../support/web-layout.page';
import { RecordModalPage } from '../support/record-modal.page';
import { DEMO_STATUS_PAGE_TITLE, DEMO_ASSIGNEE_USERNAME } from '../support/demo-content';

/*
 * Covers the merged Comments/Assignee modal (record-modal.js): both header triggers now open
 * the same Modal.advanced() instance with a tab bar, each preselecting its own tab, and
 * switching tabs must not disturb the other tab's already-loaded content.
 *
 * Uses the status-page fixture (status, assignee and a comment all seeded, see
 * support/demo-content.ts) so both tabs have real content to assert against.
 */

test('the comments trigger opens the modal on the Comments tab and Assignee loads lazily on switch', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await webLayout.commentsListButton().click();

  const recordModal = new RecordModalPage(page);
  await expect(recordModal.tab('comments')).toHaveAttribute('aria-selected', 'true');
  await expect(recordModal.tab('assignee')).toHaveAttribute('aria-selected', 'false');
  await expect(recordModal.pane('comments')).toBeVisible();
  await expect(recordModal.pane('assignee')).toBeHidden();

  await recordModal.switchTo('assignee');

  await expect(recordModal.tab('assignee')).toHaveAttribute('aria-selected', 'true');
  await expect(recordModal.assigneeListbox()).toBeVisible();
  // The comments-tab trigger carries no `currentAssignee` - this is the controller-side
  // fallback to the record's own field (RecordController::assigneeSelectionAction).
  await expect(recordModal.assigneeListbox()).toContainText(DEMO_ASSIGNEE_USERNAME);

  // Switching back must not have lost the comments pane's content.
  await recordModal.switchTo('comments');
  await expect(recordModal.pane('comments').locator('.content-planner-comment__text')).not.toHaveCount(0);

  await recordModal.close();
});

test('the assignee trigger opens the modal on the Assignee tab', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await webLayout.assigneeButton().click();

  const recordModal = new RecordModalPage(page);
  await expect(recordModal.tab('assignee')).toHaveAttribute('aria-selected', 'true');
  await expect(recordModal.assigneeListbox()).toBeVisible();

  await recordModal.close();
});
