import type { Locator, Page } from '@playwright/test';

/**
 * The merged Comments/Assignee modal opened by `record-modal.js`: one `Modal.advanced()`
 * instance with a tab bar (`role="tablist"`) at the top of its content, replacing the two
 * formerly separate modals. Like `CommentsModalPage`, it always attaches to the TOP document,
 * never inside the content iframe that triggered it.
 */
export class RecordModalPage {
  constructor(private readonly page: Page) {}

  private modal(): Locator {
    return this.page.locator('.t3js-modal');
  }

  tab(name: 'comments' | 'assignee'): Locator {
    return this.modal().locator(`#content-planner-tab-${name}`);
  }

  pane(name: 'comments' | 'assignee'): Locator {
    return this.modal().locator(`#content-planner-pane-${name}`);
  }

  async switchTo(name: 'comments' | 'assignee'): Promise<void> {
    await this.tab(name).click();
    await this.pane(name).waitFor({ state: 'visible' });
  }

  assigneeSearch(): Locator {
    return this.pane('assignee').locator('[data-assignee-search]');
  }

  assigneeListbox(): Locator {
    return this.pane('assignee').locator('[data-assignee-listbox]');
  }

  async close(): Promise<void> {
    const modal = this.modal();
    await modal.locator('button[name="close"]').click();
    await modal.waitFor({ state: 'detached' });
  }
}
