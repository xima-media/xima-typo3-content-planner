import { test, expect } from '@playwright/test';
import { WebLayoutPage } from '../support/web-layout.page';
import { RecordModalPage } from '../support/record-modal.page';
import { DEMO_STATUS_PAGE_TITLE } from '../support/demo-content';

/*
 * The status menu in the record modal's context row (record-modal.js) is positioned by hand
 * instead of through Bootstrap, so its keyboard behaviour is its own: open with the arrow key,
 * move with the arrows, close with Escape and give the focus back to the button.
 */

test('the modal status menu is operable with the keyboard', async ({ page }) => {
  const webLayout = new WebLayoutPage(page);
  await webLayout.openPage(DEMO_STATUS_PAGE_TITLE);
  await webLayout.commentsListButton().click();

  const recordModal = new RecordModalPage(page);
  const toggle = recordModal.statusToggle();
  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');

  // The modal moves the focus to its own default button once it has loaded, which would take it
  // away from the menu again if the keyboard got there first.
  await expect(page.locator('.t3js-modal [data-comment-composer-trigger]').first()).toBeVisible();
  await expect(page.locator('.t3js-modal button[name="close"]')).toBeFocused();

  await toggle.focus();
  await page.keyboard.press('ArrowDown');

  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(recordModal.statusItems().first()).toBeVisible();
  // The focus is on the current status, which the menu marks.
  await expect(page.locator('.t3js-modal .content-planner-record-modal__status .dropdown-item[aria-current="true"]')).toBeFocused();
  // Named once: the icon next to the text does not repeat the name.
  const current = page.locator('.t3js-modal .content-planner-record-modal__status .dropdown-item[aria-current="true"]');
  const title = (await current.textContent())?.trim() ?? '';
  await expect(current).toHaveAccessibleName(title);

  await page.keyboard.press('ArrowDown');
  await expect(page.locator('.t3js-modal .content-planner-record-modal__status .dropdown-item:focus')).not.toHaveAttribute('aria-current', 'true');

  await page.keyboard.press('Escape');

  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect(toggle).toBeFocused();
  // Escape closes the menu only, the modal stays.
  await expect(recordModal.tab('comments')).toBeVisible();

  await recordModal.close();
});
