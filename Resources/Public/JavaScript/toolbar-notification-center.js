/**
* Module: @content-planner/toolbar-notification-center
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"

class NotificationCenter {
  constructor() {
    this.toolbarItem = null;
    this.badge = null;
    this.dropdown = null;
    document.addEventListener('DOMContentLoaded', () => this.initialize());
  }

  initialize() {
    // The core toolbar chrome (Topbar.html) only ever forwards this toolbar item's `class` onto
    // its <li> wrapper, not an `id` or arbitrary data attributes - so this is a class selector,
    // and the poll interval is read off an element inside this class's own getItem() markup
    // rather than off the <li> itself.
    this.toolbarItem = document.querySelector('.content-planner-notification-toolbar-item');
    if (!this.toolbarItem) {
      return;
    }

    this.badge = document.getElementById('content-planner-notification-badge');
    this.dropdown = document.getElementById('content-planner-notification-dropdown');

    this.toolbarItem.addEventListener('click', (event) => this.handleClick(event));

    // The dropdown ships empty so the backend chrome does not pay for a list nobody may
    // open (see NotificationToolbarItem::getDropDown()); fill it on first open.
    this.toolbarItem.addEventListener('show.bs.dropdown', () => {
      if (!this.listLoaded) {
        this.refresh();
      }
    });

    const pollIntervalHolder = this.toolbarItem.querySelector('[data-poll-interval]');
    const pollInterval = parseInt(pollIntervalHolder?.dataset.pollInterval || '0', 10);
    if (pollInterval > 0) {
      window.setInterval(() => this.refresh(), pollInterval * 1000);
    }
  }

  handleClick(event) {
    const markReadButton = event.target.closest('[data-notification-mark-read]');
    if (markReadButton) {
      event.preventDefault();
      const entry = markReadButton.closest('[data-notification-uid]');
      this.markAsRead(parseInt(markReadButton.dataset.notificationMarkRead, 10), entry);

      return;
    }

    const markAllButton = event.target.closest('[data-notification-mark-all-read]');
    if (markAllButton) {
      event.preventDefault();
      this.markAllAsRead();
    }
  }

  refresh() {
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_notifications)
      .get()
      .then(async (result) => {
        const data = await result.response.json();
        this.replaceList(data.result);
        this.updateBadge(data.unreadCount, data.badgeLabel);
        this.listLoaded = true;
      })
      .catch((error) => this.logIncompleteRequest('notification refresh', error));
  }

  markAsRead(uid, entryElement) {
    if (!uid) {
      return;
    }

    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_notifications_read)
      .withQueryArguments({uid})
      .get()
      .then(async (result) => {
        const data = await result.response.json();
        this.updateBadge(data.unreadCount, data.badgeLabel);
        this.markEntryRead(entryElement);
      })
      .catch((error) => this.logIncompleteRequest('mark-as-read', error));
  }

  markAllAsRead() {
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_notifications_read_all)
      .get()
      .then(async (result) => {
        const data = await result.response.json();
        this.updateBadge(data.unreadCount, data.badgeLabel);
        this.dropdown?.querySelectorAll('[data-notification-uid]').forEach((entry) => this.markEntryRead(entry));
      })
      .catch((error) => this.logIncompleteRequest('mark-all-as-read', error));
  }

  /**
   * A request aborted by a content frame refresh (e.g. Firefox NS_BINDING_ABORTED) is not worth
   * surfacing to the user - same rationale as the notification toast module.
   */
  logIncompleteRequest(action, error) {
    console.debug(`Content Planner: ${action} did not complete:`, error);
  }

  markEntryRead(entryElement) {
    if (!entryElement) {
      return;
    }

    entryElement.classList.add('content-planner-notification-entry--read');
    entryElement.querySelector('[data-notification-mark-read]')?.remove();
  }

  replaceList(html) {
    const list = document.getElementById('content-planner-notification-list');
    if (!list || 'string' !== typeof html) {
      return;
    }

    list.outerHTML = html;
    this.dropdown = document.getElementById('content-planner-notification-dropdown');

    // The header is rendered before the entries are known, so the "mark all read" button
    // only becomes meaningful once a list has actually arrived.
    const markAllButton = this.toolbarItem?.querySelector('[data-notification-mark-all-read]');
    if (markAllButton) {
      const hasEntries = null !== document
        .getElementById('content-planner-notification-list')
        ?.querySelector('[data-notification-uid]');
      markAllButton.hidden = !hasEntries;
    }
  }

  updateBadge(unreadCount, badgeLabel) {
    if (!this.badge) {
      return;
    }

    this.badge.textContent = unreadCount > 0 ? badgeLabel : '';
    this.badge.dataset.unreadCount = String(unreadCount);
    this.badge.classList.toggle('content-planner-notification-badge--hidden', 0 === unreadCount);
  }
}

export default new NotificationCenter()
