import { test, expect } from '@playwright/test';
import { CommentsModalPage } from '../support/comments.page';
import { RecordModalPage } from '../support/record-modal.page';
import { WebLayoutPage } from '../support/web-layout.page';
import { DEMO_ASSIGNEE_USERNAME, DEMO_DRAFT_PAGE_TITLE } from '../support/demo-content';

/*
 * The flows inside the open record modal that must not reload the page behind it: the modal
 * keeps working after a status or assignee change, and the composer starts collapsed.
 * Uses the draft fixture page (a status, no assignee, no comments - see support/demo-content.ts).
 */

test('the comments trigger opens the list with the composer collapsed', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_DRAFT_PAGE_TITLE);
  await webLayout.commentsListButton().click();

  const commentsModal = new CommentsModalPage(page);
  await expect(page.locator('.t3js-modal [data-comment-composer-trigger]').first()).toBeVisible();
  await expect(commentsModal.composer()).toBeHidden();

  await new RecordModalPage(page).close();
});

test('a comment can switch the status and the status control follows', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_DRAFT_PAGE_TITLE);
  await webLayout.commentsListButton().click();

  const recordModal = new RecordModalPage(page);
  const commentsModal = new CommentsModalPage(page);
  await commentsModal.openComposer();

  // Whatever the other specs left as the current status, the select offers the others.
  const select = page.locator('.t3js-modal #content-planner-comment-status-change');
  const target = (await select.locator('option:not([value=""])').first().textContent())?.trim() ?? '';
  await select.selectOption({ label: target });
  await commentsModal.createComment(`e2e status switch ${Date.now()}`);

  await expect(recordModal.statusToggle()).toContainText(target);
  // Still operable: the page behind is only refreshed when the modal closes.
  await recordModal.statusToggle().click();
  await expect(recordModal.statusItems().first()).toBeVisible();

  await page.keyboard.press('Escape');
  await recordModal.close();
});

test('assigning inside the open modal keeps it open and names the assignee on the tab', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_DRAFT_PAGE_TITLE);
  await webLayout.commentsListButton().click();

  const recordModal = new RecordModalPage(page);
  await recordModal.switchTo('assignee');
  await expect(recordModal.assigneeListbox()).toBeVisible();

  const assignToMe = recordModal.pane('assignee').getByRole('button', { name: /assign to me/i });
  const unassign = recordModal.pane('assignee').getByRole('button', { name: /unassign/i });
  // Another spec may have left the page assigned to this user already.
  if (!(await assignToMe.isVisible())) {
    await unassign.click();
    await expect(assignToMe).toBeVisible();
  }
  await assignToMe.click({ force: true });

  await expect(recordModal.tab('assignee')).toContainText(DEMO_ASSIGNEE_USERNAME);
  await expect(recordModal.pane('assignee').locator('.content-planner-assignee__person')).toContainText(DEMO_ASSIGNEE_USERNAME);

  await recordModal.close();
});
