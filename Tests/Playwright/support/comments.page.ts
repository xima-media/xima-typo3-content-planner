import type { Locator, Page } from '@playwright/test';

/**
 * The comments modal opened from `Resources/Private/Templates/Backend/Header/HeaderInfo.html`
 * (`comments-list-modal.js`). A TYPO3 `Modal.advanced()` instance, which always attaches to the
 * TOP document - never inside the content iframe that triggered them - so locators here use
 * `page`, not a content frame.
 *
 * Since CP-28 (#327) there is no second modal and no iframe: creating, editing and replying all
 * happen inline in this one list view through `form[data-comment-composer]`, a
 * `typo3-rte-ckeditor-ckeditor5` element submitted over AJAX rather than FormEngine's `_savedok`.
 * The header button carrying `data-focus-composer` opens the list with that composer already
 * expanded, which is the "add a comment" entry point.
 */
export class CommentsModalPage {
  constructor(private readonly page: Page) {}

  /** The inline composer for a new comment, as opposed to an edit or reply composer. */
  composer(): Locator {
    return this.page.locator('form[data-comment-composer][data-mode="new"]');
  }

  /**
   * Types into the CKEditor instance of the new-comment composer and submits it.
   *
   * Waits for the composer to leave the pending state afterwards rather than for the modal to
   * close: the modal stays open and reconciles the saved comment into the list in place, so
   * there is no detach to wait on and a fixed sleep would either race the request or mask a
   * failure to save.
   */
  async createComment(text: string): Promise<void> {
    const composer = this.composer();
    await composer.waitFor({ state: 'visible' });

    const editable = composer.locator('.ck-editor__editable');
    await editable.waitFor({ state: 'visible' });
    await editable.fill(text);

    await composer.locator('[data-comment-composer-submit]').click();
  }

  /** Text content of every comment currently rendered in the open list. */
  commentTexts(): Locator {
    return this.page.locator('.content-planner-comment__text');
  }

  /** Closes the modal through its footer button; `staticBackdrop` rules out a click-away. */
  async close(): Promise<void> {
    const modal = this.page.locator('.t3js-modal');
    await modal.locator('button[name="close"]').click();
    await modal.waitFor({ state: 'detached' });
  }
}
