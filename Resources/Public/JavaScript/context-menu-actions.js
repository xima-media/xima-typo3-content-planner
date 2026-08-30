/**
* Module: @content-planner/context-menu-actions
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Viewport from "@typo3/backend/viewport.js";
import CommentsModal from "@content-planner/comments-list-modal.js";
import AssigneeModal from "@content-planner/assignee-selection-modal.js";
import Notification from "@content-planner/notification.js";
import OptimisticUpdate from "@content-planner/optimistic-update.js";

const FOLDER_TABLE = 'tx_ximatypo3contentplanner_folder';

class ContextMenuActions {

  change(table, uid, n) {
    const effectiveTable = n.effectiveTable || table;
    const effectiveUid = n.effectiveUid || uid;
    ContextMenuActions.changeStatus(effectiveTable, effectiveUid, n.status, n.folderStatusUrl, {
      title: n.statusTitle,
      color: n.statusColor,
      icon: n.statusIcon,
    });
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

  /**
   * Fires the status-change request and applies it optimistically: the status header badge
   * (rendered inside the content iframe) is updated immediately, before the request resolves.
   * On success the tree is refreshed (targeted for pages, see refreshPageTreeNode); on failure
   * the optimistic change is rolled back and an error is shown. Replaces the previous
   * unconditional `Viewport.ContentContainer.refresh()`, which reloaded the whole content
   * iframe and lost scroll position, open panels and form state on every status change.
   */
  static changeStatus(table, uid, status, folderStatusUrl, statusMeta) {
    let url;

    // For folders, use the custom folder status endpoint
    if (folderStatusUrl) {
      url = folderStatusUrl + "&status=" + (status || "0");
    } else {
      url = top.TYPO3.settings.RecordCommit.moduleUrl + "&data[" + table + "][" + uid + "][tx_ximatypo3contentplanner_status]=" + status;
    }

    const isReset = status === "" || status === "0";
    const messageKey = isReset ? "status.reset" : "status.changed";

    OptimisticUpdate.run({
      // Rapid successive changes to the same record must not let a slow, failing
      // request roll back over the result of a newer one.
      scope: table + ":" + uid,
      apply: () => ContextMenuActions.applyStatusOptimistically(table, uid, isReset, statusMeta),
      request: () => new AjaxRequest(url).get().then(result => {
        if (!result.response.ok) {
          throw new Error("Content Planner: status change request failed");
        }
        return result;
      }),
      rollback: snapshot => ContextMenuActions.rollbackStatus(snapshot),
    }).then(() => {
      Notification.message(messageKey, "success");
      ContextMenuActions.refreshTreeForTable(table, uid);
    }).catch(() => {
      Notification.message(messageKey, "failure");
    });
  }

  /**
   * Only the page tree gets a targeted, single-node refresh (see refreshPageTreeNode). The
   * file storage tree has no equivalent partial-refresh hook in TYPO3 core, so folders keep
   * the existing full-tree refresh event.
   */
  static refreshTreeForTable(table, uid) {
    if ("pages" === table) {
      ContextMenuActions.refreshPageTreeNode(uid);
    }

    if (FOLDER_TABLE === table) {
      top.document.dispatchEvent(new CustomEvent("typo3:filestoragetree:refresh"));
    }
  }

  /**
   * Refreshes only the affected page's row in the page tree instead of the whole tree.
   * TYPO3 core has no public "refresh this one node" event, so this reaches into the page
   * tree component's own tree instance (the same loadChildren()/getParentNode() pair core
   * itself uses internally after creating a page, see page-tree-element.js) and forces a
   * refetch of just the affected node's parent's children. If the tree component, the node,
   * or its parent cannot be resolved (e.g. a root page has no parent, or TYPO3 core changes
   * these internals), this falls back to the previous full `typo3:pagetree:refresh` event so
   * the tree never goes stale.
   */
  static async refreshPageTreeNode(pageId) {
    if (await ContextMenuActions.tryRefreshPageTreeNodeOnly(pageId)) {
      return;
    }

    top.document.dispatchEvent(new CustomEvent("typo3:pagetree:refresh"));
  }

  static async tryRefreshPageTreeNodeOnly(pageId) {
    try {
      const treeElement = top.document.querySelector("typo3-backend-navigation-component-pagetree");
      const tree = treeElement && treeElement.tree;

      if (!tree || "function" !== typeof tree.loadChildren || "function" !== typeof tree.getParentNode) {
        return false;
      }

      const node = tree.nodes.find(candidate => candidate.identifier === String(pageId));
      const parentNode = node && tree.getParentNode(node);

      if (!parentNode) {
        return false;
      }

      // Force a refetch: loadChildren() only recurses into already-loaded children otherwise.
      parentNode.loaded = false;
      // Awaited so a rejection lands in the catch below and the caller can still fall back to
      // the full-tree refresh; without it the node could silently stay stale.
      await tree.loadChildren(parentNode);

      return true;
    } catch (error) {
      console.debug("Content Planner: targeted page tree refresh failed, falling back to full refresh:", error);
      return false;
    }
  }

  /**
   * Applies the new status to the status header badge (icon, colour, title, aria-label)
   * immediately, without waiting for the request to resolve. Returns a snapshot for
   * rollbackStatus(), or null when there is nothing to roll back (no matching badge is
   * currently visible, e.g. the changed record isn't the one open in the content area).
   */
  static applyStatusOptimistically(table, uid, isReset, statusMeta) {
    const contentWindow = ContextMenuActions.getContentWindow();
    const header = contentWindow && ContextMenuActions.findStatusHeader(contentWindow.document, table, uid);

    if (!header) {
      return null;
    }

    const snapshot = {
      header,
      className: header.className,
      ariaLabel: header.getAttribute("aria-label"),
      // Note: the `hidden` attribute is not used here because `.content-planner-header` sets
      // its own `display: flex` in Header.css, which (same specificity, but author origin)
      // overrides the user-agent `[hidden]` rule. An inline style is required to actually hide it.
      display: header.style.display,
      titleHTML: header.querySelector(".content-planner-header__body strong")?.outerHTML ?? null,
      iconHTML: header.querySelector(".content-planner-header__left")?.innerHTML ?? null,
    };

    if (isReset) {
      header.style.display = "none";
      return snapshot;
    }

    if (!statusMeta || !statusMeta.title) {
      // No status metadata available for this entry: nothing to render optimistically, so
      // don't touch the DOM (and don't schedule a no-op rollback for it either).
      return null;
    }

    header.style.display = "";
    header.className = `content-planner-header content-planner-header--color-${statusMeta.color}`;

    const previousAriaLabel = snapshot.ariaLabel || "";
    header.setAttribute("aria-label", previousAriaLabel.replace(/:[^:]*$/, `: ${statusMeta.title}`) || `Status: ${statusMeta.title}`);

    const titleElement = header.querySelector(".content-planner-header__body strong");
    if (titleElement) {
      titleElement.textContent = statusMeta.title;
    }

    const iconContainer = header.querySelector(".content-planner-header__left");
    if (iconContainer && statusMeta.icon) {
      const icon = header.ownerDocument.createElement("typo3-backend-icon");
      icon.setAttribute("identifier", statusMeta.icon);
      icon.setAttribute("size", "medium");
      iconContainer.replaceChildren(icon);
    }

    return snapshot;
  }

  /**
   * Undoes applyStatusOptimistically() using the snapshot it returned.
   */
  static rollbackStatus(snapshot) {
    if (!snapshot) {
      return;
    }

    const {header, className, ariaLabel, display, titleHTML, iconHTML} = snapshot;

    header.className = className;
    header.style.display = display;

    if (null === ariaLabel) {
      header.removeAttribute("aria-label");
    } else {
      header.setAttribute("aria-label", ariaLabel);
    }

    const titleElement = header.querySelector(".content-planner-header__body strong");
    if (titleElement && titleHTML) {
      titleElement.outerHTML = titleHTML;
    }

    const iconContainer = header.querySelector(".content-planner-header__left");
    if (iconContainer && null !== iconHTML) {
      iconContainer.innerHTML = iconHTML;
    }
  }

  static getContentWindow() {
    try {
      return Viewport.ContentContainer.get();
    } catch (error) {
      return null;
    }
  }

  static findStatusHeader(doc, table, uid) {
    if (!doc) {
      return null;
    }

    return doc.querySelector(`.content-planner-header[data-table="${table}"][data-uid="${uid}"]`);
  }
}

export default new ContextMenuActions();
