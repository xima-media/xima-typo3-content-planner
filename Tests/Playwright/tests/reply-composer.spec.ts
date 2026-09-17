import { test, expect } from '@playwright/test';
import { WebLayoutPage } from '../support/web-layout.page';
import { DEMO_STATUS_PAGE_TITLE } from '../support/demo-content';

/*
 * Reply composer layout (CP-28, #327).
 *
 * The reply editor used to be appended to a bare slot one level above the composer row, which
 * dropped it a padding, an avatar column and a gap further left than the "Add a reply..." it
 * replaced. It now opens inside that row's body, next to the avatar, the same way the
 * new-comment composer does.
 *
 * Geometry rather than a screenshot: the alignment is the whole point, and comparing the
 * editor's left edge against the trigger's states that directly.
 */

test('the reply editor opens where its trigger was, beside the avatar', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await webLayout.commentsListButton().click();

  await page.locator('.content-planner-comment-replies-toggle').first().click();

  const trigger = page.locator('.content-planner-comment-composer-trigger--reply').first();
  await expect(trigger).toBeVisible();
  const triggerBox = await trigger.boundingBox();

  const avatar = page
    .locator('.content-planner-comment-composer-row--reply .content-planner-comment-composer-row__avatar')
    .first();
  await expect(avatar).toBeVisible();

  await trigger.click();

  const form = page.locator('[data-reply-slot] [data-comment-composer]');
  await expect(form).toBeVisible();

  // The avatar column is what the old markup lost: the row survives, only its trigger goes.
  await expect(avatar).toBeVisible();
  await expect(trigger).toBeHidden();

  const formBox = await form.boundingBox();
  expect(Math.round(formBox!.x)).toBe(Math.round(triggerBox!.x));
  expect(Math.round(formBox!.width)).toBe(Math.round(triggerBox!.width));
});

test('cancelling the reply editor brings its trigger back', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);

  await webLayout.commentsListButton().click();
  await page.locator('.content-planner-comment-replies-toggle').first().click();

  const trigger = page.locator('.content-planner-comment-composer-trigger--reply').first();
  await trigger.click();

  const form = page.locator('[data-reply-slot] [data-comment-composer]');
  await expect(form).toBeVisible();

  await form.locator('[data-comment-composer-cancel]').click();

  await expect(form).toHaveCount(0);
  await expect(trigger).toBeVisible();
});
