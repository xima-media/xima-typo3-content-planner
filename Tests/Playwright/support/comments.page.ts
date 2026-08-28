import type { FrameLocator, Locator, Page } from '@playwright/test';

/**
 * The two comment modals opened from `Resources/Private/Templates/Backend/Header/HeaderInfo.html`
 * (`create-and-edit-comment-modal.js` / `comments-list-modal.js`). Both are TYPO3
 * `Modal.advanced()` instances, which always attach to the TOP document - never inside
 * the content iframe that triggered them - so locators here use `page`, not a content frame.
 */
export class CommentsModalPage {
  constructor(private readonly page: Page) {}

  private modalIframe(): FrameLocator {
    return this.page.frameLocator('.modal-body iframe');
  }

  /**
   * Fills the CKEditor-backed "content" field of the create-comment form (loaded in the
   * modal's iframe, an ordinary FormEngine record-edit route) and saves it.
   *
   * Waits for the modal iframe to detach afterwards: `create-and-edit-comment-modal.js`
   * closes the modal itself once it observes the iframe reload after submit, so a
   * fixed sleep here would either race that or hide a real failure to save.
   */
  async createComment(text: string): Promise<void> {
    const editable = this.modalIframe().locator('.ck-editor__editable');
    await editable.waitFor({ state: 'visible' });
    await editable.fill(text);
    await this.modalIframe().locator('button[name="_savedok"]').click();
    await this.page.locator('.modal-body iframe').waitFor({ state: 'detached', timeout: 15_000 });
  }

  /** Text content of every comment currently rendered in the open "list comments" modal. */
  commentTexts(): Locator {
    return this.page.locator('.content-planner-comment__text');
  }
}
