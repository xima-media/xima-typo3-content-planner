import { expect, type Locator, type Page } from '@playwright/test';

/**
 * Page-tree context-menu "Content Status" submenu, contributed by
 * `Classes/Backend/ContextMenu/ItemProviders/StatusItemProvider.php`. TYPO3 core's
 * `AbstractProvider` names submenu entries `root_<key>` for the submenu itself and
 * `root_<key>_<itemKey>` for its children - `StatusItemProvider` names its submenu
 * `wrap`, so `root_wrap` / `root_wrap_<n>` (verified against the rendered DOM, not
 * documented by core).
 */
export class StatusMenuPage {
  constructor(private readonly page: Page) {}

  private submenuTrigger(): Locator {
    return this.page.locator('[data-contextmenu-id="root_wrap"]');
  }

  /** Right-clicks the given page-tree node and opens its "Content Status" submenu. */
  async openFor(node: Locator): Promise<void> {
    await node.click({ button: 'right' });
    await this.submenuTrigger().waitFor({ state: 'visible' });
    await this.submenuTrigger().hover();
    // Hovering alone is flaky for expanding this particular submenu - a follow-up
    // click reliably forces it open (verified empirically against TYPO3 14.3.6).
    await this.submenuTrigger().click();
  }

  /** Clicks a status entry in the already-open submenu by its visible label, e.g. "Needs review". */
  async chooseStatus(statusTitle: string): Promise<void> {
    const item = this.page.getByRole('menuitem', { name: statusTitle, exact: true });
    await item.waitFor({ state: 'visible' });
    await item.click();
  }

  /**
   * Waits for the node's colour chip to settle on `expectedColor` (a `rgb(r, g, b)`
   * string as returned by getComputedStyle).
   *
   * Needed because `context-menu-actions.js` applies the status change through an
   * AJAX request (`OptimisticUpdate.run()`) and then a *targeted* page-tree refresh
   * (`refreshPageTreeNode()`, which refetches only the affected node's parent via
   * `tree.loadChildren()`) - both asynchronous, and deliberately not a full page or
   * tree reload, so a plain assertion right after the click would race the update.
   */
  async waitForNodeLabelColor(node: Locator, expectedColor: string): Promise<void> {
    await expect(node.locator('.node-label')).toHaveCSS('background-color', expectedColor, {
      timeout: 10_000,
    });
  }
}
