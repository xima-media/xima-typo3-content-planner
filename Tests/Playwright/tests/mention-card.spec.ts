import { test, expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { WebLayoutPage } from '../support/web-layout.page';
import { DEMO_ASSIGNEE_USERNAME, DEMO_STATUS_PAGE_TITLE } from '../support/demo-content';

/*
 * Profile card behind a rendered @-mention (#305).
 *
 * This lives in the e2e suite rather than in a unit test because nothing below this level can
 * catch the failure it was written for: the PHP side renders a correct `button.ctp-mention`
 * and the AJAX endpoint returns a correct card, but `comment-mention-card.js` built its
 * Bootstrap Popover instance before the element carried any content. Bootstrap reads the
 * content once, at construction, so `show()` returned in `_isWithContent()` and no card was
 * ever rendered - by hover, by focus or by click.
 */

/** Opens the comment list of the seeded status page and returns its first rendered mention. */
async function openMention(page: Page): Promise<Locator> {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await webLayout.commentsListButton().click();

  // Scoped away from the reply list, which the comment list renders collapsed.
  const mention = page
    .locator('.content-planner-comment:not(.content-planner-comment--reply) .ctp-mention')
    .first();
  await expect(mention).toBeVisible();

  return mention;
}

/**
 * Asserts the card shows the resolved user rather than the loading placeholder, which only
 * holds once the AJAX response has replaced the popover's content.
 */
async function expectCard(page: Page): Promise<void> {
  const card = page.locator('.content-planner-mention-card');
  await expect(card).toBeVisible();
  await expect(card.locator('.content-planner-mention-card__username')).toHaveText(
    DEMO_ASSIGNEE_USERNAME,
  );
}

test('hovering a mention opens the profile card', async ({ page }) => {
  const mention = await openMention(page);

  await mention.hover();

  await expectCard(page);
});

test('focusing a mention opens the profile card', async ({ page }) => {
  const mention = await openMention(page);

  // The reason MentionUtility renders a button rather than the href-less anchor it replaced:
  // without focus there is no keyboard route to the card at all.
  await mention.focus();

  await expectCard(page);
});
