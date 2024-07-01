/**
 * Module: @xima/ximatypo3contentplanner/context-menu-actions
 *
 * JavaScript to handle the click action of the "Hello World" context menu item
 */
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

class ContextMenuActions {

  danger(table, uid) {
    ContextMenuActions.changeStatus(table, uid, "danger");
  }

  info(table, uid) {
    ContextMenuActions.changeStatus(table, uid, "info");
  }

  warning(table, uid) {
    ContextMenuActions.changeStatus(table, uid, "warning");
  }

  success(table, uid) {
    ContextMenuActions.changeStatus(table, uid, "success");
  }

  reset(table, uid) {
    ContextMenuActions.changeStatus(table, uid, "");
  }

  static changeStatus(table, uid, status) {
    if (table === 'pages') {
      new AjaxRequest(top.TYPO3.settings.RecordCommit.moduleUrl + "&data[" + table + "][" + uid + "][tx_ximatypo3contentplanner_status]=" + status)
        .get()
        .then(function (result) {
          if (result.response.ok) {
            top.TYPO3.Notification.success('Status', 'Page status successfully changed.');
          } else {
            top.TYPO3.Notification.error('Status', 'Error changing page status.');
          }

          top.document.dispatchEvent(new CustomEvent("typo3:pagetree:refresh"));
        });

    }
  };
}

export default new ContextMenuActions();
