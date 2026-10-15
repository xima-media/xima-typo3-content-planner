import type { FrameLocator, Locator, Page } from '@playwright/test';
import { BackendPage, PageTreePage } from '@konradmichalik/ptu';

const CONTENT_IFRAME = '#typo3-contentIframe';

/**
 * Web > Page (`web_layout`) status surface. In the default `chip` display mode it is the
 * Content Planner button group in the doc header, added by
 * `Classes\EventListener\ModifyButtonBarEventListener` /
 * `Classes\Service\Header\ChipTrioButtonBuilder`: the status dropdown, the assignee button and
 * the comments button. The full-width banner (`.content-planner-header`) only exists in
 * `banner` mode and is not covered here.
 *
 * TYPO3 renders module content in `#typo3-contentIframe`, so every locator here is
 * scoped through `frameLocator()`, which re-resolves on each access and therefore
 * survives that iframe reloading (e.g. after `Viewport.ContentContainer.refresh()`
 * following a comment/status change).
 */
export class WebLayoutPage {
  constructor(private readonly page: Page) {}

  private content(): FrameLocator {
    return this.page.frameLocator(CONTENT_IFRAME);
  }

  /** Opens Web > Page for the page with this title via the page tree. */
  async openPage(title: string): Promise<void> {
    await new BackendPage(this.page).openModule('web/layout');
    const pageTree = new PageTreePage(this.page);
    await pageTree.search(title);
    const node = pageTree.node(title);
    await node.waitFor({ state: 'visible' });
    await pageTree.clear();
    await node.click();
    // The module opened without a page has no Content Planner buttons, so this only resolves
    // once the selected page's doc header has rendered.
    await this.commentsButton().waitFor({ state: 'visible', timeout: 20_000 });
  }

  docHeader(): Locator {
    return this.content().locator('.module-docheader');
  }

  /** The status dropdown, labelled with the current status title. */
  statusButton(statusTitle: string): Locator {
    return this.docHeader().locator('button.dropdown-toggle').filter({ hasText: statusTitle });
  }

  /**
   * Shows the display name, so the username is asserted via its text. `.btn` skips the copy
   * core also renders in the doc header's overflow menu (`dropdown-item`).
   */
  assigneeButton(): Locator {
    return this.docHeader().locator('.btn[data-content-planner-assignees]');
  }

  /**
   * Opens the record modal. Its label carries the count ("3 Comment(s)"), and only the bare
   * label while the page has no comments.
   */
  commentsButton(): Locator {
    return this.docHeader().locator('.btn[data-content-planner-comments]');
  }

  commentsListButton(): Locator {
    return this.commentsButton();
  }
}
