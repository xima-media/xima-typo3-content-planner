/**
* Module: @xima/ximatypo3contentplanner/filter-status
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import CommentsModal from "@xima/ximatypo3contentplanner/comments-list-modal.js";

class FilterStatus {

  constructor() {
    document.addEventListener('widgetContentRendered', function(event) {
      if (event.target.querySelector('.content-planner-widget')) {
        const widget = event.target.querySelector('.content-planner-widget');

        // Skip server-rendered widgets (TYPO3 v14+ configurable widget)
        if (widget.classList.contains('widget-contentPlanner-status--server-rendered')) {
          FilterStatus.initCommentLinks(widget);
          return;
        }

        let currentBackendUser = event.target.querySelector('input[name="currentBackendUser"]')?.value || false;
        let todo = event.target.querySelector('input[name="todo"]')?.value || false;

        if (currentBackendUser) {
          FilterStatus.search(widget, {assignee: currentBackendUser}, () => {
            const badge = widget.querySelector('.content-planner-widget__description .badge');
            if (badge) {
              badge.innerHTML = widget.querySelectorAll('.widget-table tbody tr').length;
            }
          });
          widget.classList.add('widget-contentPlanner-status--assigned');
        } else if (todo && todo !== 'false') {
          FilterStatus.search(widget, {todo: true});
          widget.classList.add('content-planner-widget--todo');
        } else {
          FilterStatus.search(widget);
          let queryArguments = {};
          const form = event.target.querySelector('.content-planner-widget__filter-form');
          const search = event.target.querySelector('input[name="search"]');
          if (form && search) {
            form.addEventListener('change', function(event) {
              queryArguments[event.target.name] = event.target.value;
              FilterStatus.search(widget, queryArguments);
            });
            search.addEventListener('input', function(event) {
              queryArguments[search.name] = search.value;
              FilterStatus.search(widget, queryArguments);
            });
            form.querySelector('.content-planner-widget__filter-reset')?.addEventListener('click', function(event) {
              form.reset();
              queryArguments = {};
              FilterStatus.search(widget, queryArguments);
            });
          }
        }

      }
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

  static search(widget, queryArguments = {}, callback) {
    widget.querySelector('thead').classList.remove('content-planner-hide');
    widget.querySelector('.content-planner-widget__empty').classList.add('content-planner-hide');
    const waitingElement = widget.parentElement.parentElement.querySelector('.widget-waiting');
    if (waitingElement) {
      waitingElement.classList.remove('content-planner-hide');
    }
    widget.querySelector('.content-planner-widget__table-wrapper').classList.add('content-planner-hide');
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_filterrecords)
      .withQueryArguments(queryArguments)
      .get()
      .then(async (response) => {
        const resolved = await response.resolve();

        let html = '';
        if (resolved.length === 0) {
          widget.querySelector('.content-planner-widget__empty').classList.remove('content-planner-hide');
          widget.querySelector('thead').classList.add('content-planner-hide');
        }
        resolved.forEach(function (item) {
          let comments = '';
          if (item.data.tx_ximatypo3contentplanner_comments > 0) {
            comments = '<a href="' + TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments  + '" class="content-planner-link--comments" data-table="' + item.data.tablename + '" data-id="' + item.data.uid + '">' + item.comments + '</a>';
            if (item.todo !== '') {
              comments += ' <a href="' + TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments  + '" class="content-planner-link--comments" data-table="' + item.data.tablename + '" data-id="' + item.data.uid + '">' + item.todo + '</a>';
            }
          }

          html += '<tr ' + (item.assignedToCurrentUser ? 'class="content-planner-row--current"' : '') + '>' +
            '<td><a href="' + item.link + '">' + item.statusIcon + ' ' + item.recordIcon + ' <strong>' + item.title + '</strong></a></td>' +
            '<td>' + (item.site ?? '') + '</td>' +
            '<td><small>' + item.updated + '</small></td>' +
            '<td>' + (item.assignee ? (item.assigneeAvatar + item.assigneeName) : '') + '</td>' +
            '<td>' + comments + '</td>' +
            '</tr>';
        });
        let table = widget.querySelector('table tbody');
        table.innerHTML = html;
        if (waitingElement) {
          waitingElement.classList.add('content-planner-hide');
        }
        widget.querySelector('.content-planner-widget__table-wrapper').classList.remove('content-planner-hide');

        FilterStatus.initCommentLinks(widget);

        if (callback) {
          callback();
        }
      });
  }
}

export default new FilterStatus();
