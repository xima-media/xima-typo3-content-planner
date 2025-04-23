/**
* Module: @xima/ximatypo3contentplanner/context-menu-actions
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Viewport from "@typo3/backend/viewport.js";
import CommentsModal from "@xima/ximatypo3contentplanner/comments-list-modal.js";

class ContextMenuActions {

  change(table, uid, n) {
    ContextMenuActions.changeStatus(table, uid, n.status);
  }

  reset(table, uid) {
    ContextMenuActions.changeStatus(table, uid, "");
  }

  load(table, uid, n) {
    Viewport.ContentContainer.setUrl(n.uri);
  }

  comments(table, uid, n) {
    CommentsModal.fetchComments(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments, table, uid, n.newCommentUri, n.editUri);
  }

  static changeStatus(table, uid, status) {
    new AjaxRequest(top.TYPO3.settings.RecordCommit.moduleUrl + "&data[" + table + "][" + uid + "][tx_ximatypo3contentplanner_status]=" + status)
      .get()
      .then(function (result) {
        if (result.response.ok) {
          top.TYPO3.Notification.success('Status', 'Page status successfully changed.');
        } else {
          top.TYPO3.Notification.error('Status', 'Error changing page status.');
        }

        if (table === 'pages') {
          top.document.dispatchEvent(new CustomEvent("typo3:pagetree:refresh"));
        } else {
          top.document.location.reload();
        }
      });
  };
}

export default new ContextMenuActions();
