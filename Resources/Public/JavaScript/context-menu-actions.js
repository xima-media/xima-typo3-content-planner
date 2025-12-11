/**
* Module: @xima/ximatypo3contentplanner/context-menu-actions
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Viewport from "@typo3/backend/viewport.js";
import CommentsModal from "@xima/ximatypo3contentplanner/comments-list-modal.js";
import AssigneeModal from "@xima/ximatypo3contentplanner/assignee-selection-modal.js";
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class ContextMenuActions {

  change(table, uid, n) {
    const effectiveTable = n.effectiveTable || table;
    const effectiveUid = n.effectiveUid || uid;
    ContextMenuActions.changeStatus(effectiveTable, effectiveUid, n.status, n.folderStatusUrl);
  }

  reset(table, uid, n) {
    const effectiveTable = n.effectiveTable || table;
    const effectiveUid = n.effectiveUid || uid;
    ContextMenuActions.changeStatus(effectiveTable, effectiveUid, "", n.folderStatusUrl);
  }

  load(table, uid, n) {
    Viewport.ContentContainer.setUrl(n.uri);
  }

  comments(table, uid, n) {
    const effectiveTable = n.effectiveTable || table;
    const effectiveUid = n.effectiveUid || uid;
    CommentsModal.fetchComments(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments, effectiveTable, effectiveUid, n.newCommentUri, n.editUri);
  }

  assignee(table, uid, n) {
    const effectiveTable = n.effectiveTable || table;
    const effectiveUid = n.effectiveUid || uid;
    AssigneeModal.fetchUsers(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_assignees, effectiveTable, effectiveUid, n.currentAssignee);
  }

  static changeStatus(table, uid, status, folderStatusUrl) {
    let url;

    // For folders, use the custom folder status endpoint
    if (folderStatusUrl) {
      url = folderStatusUrl + "&status=" + (status || "0");
    } else {
      url = top.TYPO3.settings.RecordCommit.moduleUrl + "&data[" + table + "][" + uid + "][tx_ximatypo3contentplanner_status]=" + status;
    }

    new AjaxRequest(url)
      .get()
      .then(function (result) {
        Notification.message(
          status === "" || status === "0" ? "status.reset" : "status.changed",
          result.response.ok ? "success" : "failure"
        )

        if (table === 'pages') {
          top.document.dispatchEvent(new CustomEvent("typo3:pagetree:refresh"));
        }

        if (table === 'tx_ximatypo3contentplanner_folder') {
          top.document.dispatchEvent(new CustomEvent("typo3:filestoragetree:refresh"))
        }

        Viewport.ContentContainer.refresh();
      })
  }
}

export default new ContextMenuActions();
