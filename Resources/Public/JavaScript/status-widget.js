/**
* Module: @content-planner/status-widget
*
* The record list of the status widget: loads the latest records over AJAX and renders the rows.
* Searching and filtering is the job of the records module, the widget only links there.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import CommentsModal from "@content-planner/comments-list-modal.js";

class StatusWidget {

  constructor() {
    document.addEventListener('widgetContentRendered', function(event) {
      const widget = event.target.querySelector('.content-planner-widget');
      if (!widget) {
        return;
      }

      // Server-rendered widgets (TYPO3 v14+ configurable widget) only need their comment links.
      if (widget.classList.contains('content-planner-widget--server-rendered')) {
        StatusWidget.initCommentLinks(widget);
        return;
      }

      // The KPI widget has no per-record list, its figures are computed server-side.
      if (widget.classList.contains('content-planner-widget--kpi')) {
        return;
      }

      StatusWidget.load(widget);
    });
  }

  static initCommentLinks(widget) {
    widget.querySelectorAll('.content-planner-link--comments').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        const url = e.currentTarget.getAttribute('href');
        const table = e.currentTarget.getAttribute('data-table');
        const id = e.currentTarget.getAttribute('data-id');
        CommentsModal.fetchComments(url, table, id);
      });
    });
  }

  static escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[char]);
  }

  static load(widget) {
    widget.querySelector('thead')?.classList.remove('content-planner-hide');
    widget.querySelector('.content-planner-widget__empty')?.classList.add('content-planner-hide');
    const waitingElement = widget.querySelector('.content-planner-widget__loading');
    waitingElement?.classList.remove('content-planner-hide');
    widget.querySelector('.content-planner-widget__table-wrapper')?.classList.add('content-planner-hide');
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_latestrecords)
      .get()
      .then(async (response) => {
        const resolved = await response.resolve();
        const items = resolved.items ?? [];
        const hasMore = resolved.hasMore ?? false;

        let html = '';
        if (items.length === 0) {
          widget.querySelector('.content-planner-widget__empty')?.classList.remove('content-planner-hide');
          widget.querySelector('thead')?.classList.add('content-planner-hide');
        }
        items.forEach(function (item) {
          let comments = '';
          if (item.data.tx_ximatypo3contentplanner_comments > 0) {
            comments = '<a href="' + TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments  + '" class="content-planner-link--comments" data-table="' + item.data.tablename + '" data-id="' + item.data.uid + '">' + item.comments + '</a>';
            if (item.todo !== '' && item.todoShareUrl) {
              comments += '<a href="' + item.todoShareUrl + '">' + item.todo + '</a>';
            } else if (item.todo !== '') {
              comments += '<a href="' + TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments  + '" class="content-planner-link--comments" data-table="' + item.data.tablename + '" data-id="' + item.data.uid + '">' + item.todo + '</a>';
            }
          }

          const tag = item.assignedToCurrentUser
            ? '<span class="content-planner-tag">' + StatusWidget.escapeHtml(TYPO3.lang?.['records.tag.assignedToYou'] || 'Assigned to you') + '</span>'
            : '';
          const path = item.pagePath
            ? '<div class="content-planner-widget__path" title="' + StatusWidget.escapeHtml(item.pagePath) + '">' + StatusWidget.escapeHtml(item.pagePath) + '</div>'
            : '';
          const badge = item.statusColor
            ? '<span class="content-planner-status-badge content-planner-status content-planner-status--' + StatusWidget.escapeHtml(item.statusColor) + '">' + item.statusIcon + '<span class="content-planner-status-badge__name">' + StatusWidget.escapeHtml(item.status ?? '') + '</span></span>'
            : '';
          const assignee = item.assignee
            ? '<span title="' + item.assigneeName + '">' + item.assigneeAvatar + ' ' + StatusWidget.escapeHtml(item.assigneeShortName) + '</span>'
            : '<span class="content-planner-widget__muted">' + StatusWidget.escapeHtml(TYPO3.lang?.['header.unassigned'] || 'Unassigned') + '</span>';

          html += '<tr>' +
            '<td><div class="content-planner-widget__title-line"><a href="' + item.link + '">' + item.recordIcon + ' <strong>' + StatusWidget.escapeHtml(item.title) + '</strong></a>' + tag + '</div>' + path + '</td>' +
            '<td>' + badge + '</td>' +
            '<td>' + assignee + '</td>' +
            '<td><small title="' + item.updatedRaw + '">' + item.updated + '</small></td>' +
            '<td>' + comments + '</td>' +
            '</tr>';
        });
        let table = widget.querySelector('table tbody');
        if (table) {
          table.innerHTML = html;
        }

        const moreResults = widget.querySelector('.content-planner-widget__more-results');
        if (moreResults) {
          moreResults.hidden = !hasMore;
        }

        waitingElement?.classList.add('content-planner-hide');
        widget.querySelector('.content-planner-widget__table-wrapper')?.classList.remove('content-planner-hide');

        StatusWidget.initCommentLinks(widget);
      })
      .catch((error) => {
        // Without this, a failed request (e.g. CP-36, #408: a 500 from the endpoint) left the
        // widget permanently blank - table-wrapper still hidden from above, no empty state, no
        // spinner. Fall back to the empty state so the widget always reaches a visible terminal
        // state, and surface the error for whoever has DevTools open.
        console.error('Content Planner: failed to load status widget records:', error);
        waitingElement?.classList.add('content-planner-hide');
        widget.querySelector('thead')?.classList.add('content-planner-hide');
        widget.querySelector('.content-planner-widget__table-wrapper')?.classList.remove('content-planner-hide');
        widget.querySelector('.content-planner-widget__empty')?.classList.remove('content-planner-hide');
      });
  }
}

export default new StatusWidget();
