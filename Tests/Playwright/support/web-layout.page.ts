import type { FrameLocator, Locator, Page } from '@playwright/test';
import { BackendPage, PageTreePage } from '@konradmichalik/ptu';

const CONTENT_IFRAME = '#typo3-contentIframe';

/**
 * Web > Page (`web_layout`) status header, rendered by
 * `Classes\EventListener\DrawBackendHeaderListener` /
 * `Classes\Service\Header\InfoGenerator::generateStatusHeader()` into
 * `Resources/Private/Templates/Backend/Header/HeaderInfo.html`.
 *
 * Pre-#324 (chip-redesign) state: a single banner (`.content-planner-header`), not the
 * chip trio the CP-epic branch introduces later - see the spec file for details.
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
    await this.header().waitFor({ state: 'visible', timeout: 20_000 });
  }

  header(): Locator {
    return this.content().locator('.content-planner-header');
  }

  statusBody(): Locator {
    return this.header().locator('.content-planner-header__body');
  }

  commentsButton(): Locator {
    return this.header().locator('[data-content-planner-comments]');
  }

  commentsBadge(): Locator {
    return this.commentsButton().locator('.badge');
  }

  /**
   * CP-28 (#327) drops this id and folds the trigger into the shared
   * `[data-content-planner-comments]` button (with `data-focus-composer`), so this needs
   * updating when the CP epic and this e2e chain are merged. See `CommentsModalPage`.
   */
  newCommentLink(): Locator {
    return this.header().locator('#create-and-edit-comment-modal');
  }
}
