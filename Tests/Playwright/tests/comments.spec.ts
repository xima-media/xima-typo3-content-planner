import { test, expect } from '@playwright/test';
import { CommentsModalPage } from '../support/comments.page';
import { WebLayoutPage } from '../support/web-layout.page';
import { DEMO_DRAFT_PAGE_TITLE } from '../support/demo-content';

/*
 * Covers #313's comments flow against the CP-28 (#327) inline composer: adding a comment on a
 * page persists it (comment-composer.js -> the commentsave AJAX route -> DataHandler) and
 * increments the status header's comment count (FIELD_COMMENTS, read back through
 * CommentRepository::countAllByRecord() in InfoGenerator).
 *
 * Uses the draft fixture page (status only, no assignee, no comments - see
 * support/demo-content.ts) so the count assertion starts from a known zero rather than needing
 * to account for the pre-seeded comments on the status page.
 */

test('adding a comment persists it and increments the comment count', async ({ page }) => {
  const commentText = `e2e comment ${Date.now()}`;

  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_DRAFT_PAGE_TITLE);

  // Starting state: the comments button itself always renders (HeaderInfo.html's
  // `<f:if condition="{comments}">` checks the always-populated view-model array, not
  // the comment list) but carries no count badge yet.
  await expect(webLayout.commentsBadge()).toHaveCount(0);

  await webLayout.commentsListButton().click();

  const commentModal = new CommentsModalPage(page);
  await commentModal.createComment(commentText);

  await expect(commentModal.commentTexts()).toContainText(commentText);

  // The header lives in the content iframe and CP-28's reconcile() deliberately reloads only
  // the comment list fragment, no Viewport.ContentContainer.refresh() - so the count is
  // asserted against a freshly rendered header, which is also what proves the comment was
  // persisted rather than only painted into the open list.
  await commentModal.close();
  await webLayout.openPage(DEMO_DRAFT_PAGE_TITLE);

  await expect(webLayout.commentsBadge()).toHaveText('1');
});
