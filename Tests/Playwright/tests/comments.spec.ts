import { test, expect } from '@playwright/test';
import { CommentsModalPage } from '../support/comments.page';
import { WebLayoutPage } from '../support/web-layout.page';
import { DEMO_DRAFT_PAGE_TITLE } from '../support/demo-content';

/*
 * Covers #313's comments flow: adding a comment on a page persists it (via the
 * create-and-edit-comment-modal.js -> ordinary FormEngine record-edit route for
 * tx_ximatypo3contentplanner_comment) and increments the status header's comment
 * count (FIELD_COMMENTS, read back through CommentRepository::countAllByRecord() in
 * InfoGenerator), and the comment is visible in the comments-list-modal.js view.
 *
 * Uses the draft fixture page (status only, no assignee, no comments - see
 * support/demo-content.ts) so the count assertion starts from a known zero rather
 * than needing to account for a pre-seeded comment.
 */

test('adding a comment persists it and increments the comment count', async ({ page }) => {
  const commentText = `e2e comment ${Date.now()}`;

  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_DRAFT_PAGE_TITLE);

  // Starting state: the comments button itself always renders (HeaderInfo.html's
  // `<f:if condition="{comments}">` checks the always-populated view-model array, not
  // the comment list) but carries no count badge yet.
  await expect(webLayout.commentsBadge()).toHaveCount(0);

  await webLayout.newCommentLink().click();

  const commentModal = new CommentsModalPage(page);
  await commentModal.createComment(commentText);

  // create-and-edit-comment-modal.js refreshes the content iframe after saving
  // (Viewport.ContentContainer.refresh()), so the header re-renders from scratch here.
  await expect(webLayout.header()).toBeVisible();
  await expect(webLayout.commentsBadge()).toHaveText('1');

  await webLayout.commentsButton().click();
  await expect(commentModal.commentTexts()).toContainText(commentText);
});
