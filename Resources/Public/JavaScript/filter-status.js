/**
* Module: @xima/ximatypo3contentplanner/filter-status
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import CommentsModal from "@xima/ximatypo3contentplanner/comments-list-modal.js";

class FilterStatus {

  constructor() {
    document.addEventListener('widgetContentRendered', function(event) {
      if (event.target.querySelector('.widget-contentPlanner-status')) {
        const widget = event.target.querySelector('.widget-contentPlanner-status');
        let currentBackendUser = event.target.querySelector('input[name="currentBackendUser"]').value ? event.target.querySelector('input[name="currentBackendUser"]').value : false;

        if (currentBackendUser) {
          FilterStatus.search(widget, {assignee: currentBackendUser});
          widget.classList.add('widget-contentPlanner-status--assigned');
        } else {
          FilterStatus.search(widget);
          let queryArguments = {};
          const form = event.target.querySelector('.widget-filter-form');
          const search = event.target.querySelector('input[name="search"]');
          form.addEventListener('change', function(event) {
            queryArguments[event.target.name] = event.target.value;
            FilterStatus.search(widget, queryArguments);
          });
          search.addEventListener('input', function(event) {
            queryArguments[search.name] = search.value;
            FilterStatus.search(widget, queryArguments);
          });
          form.querySelector('.widget-filter-reset').addEventListener('click', function(event) {
            form.reset();
            queryArguments = {};
            FilterStatus.search(widget, queryArguments);
          });
        }

      }
    });
  }

  static search(widget, queryArguments = {}) {
    widget.querySelector('thead').classList.remove('hide');
    widget.querySelector('.widget-no-items-found').classList.add('hide');
    const waitingElement = widget.parentElement.parentElement.querySelector('.widget-waiting');
    if (waitingElement) {
      waitingElement.classList.remove('hide');
    }
    widget.querySelector('.widget-table-wrapper').classList.add('hide');
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_filterrecords)
      .withQueryArguments(queryArguments)
      .get()
      .then(async (response) => {
        const resolved = await response.resolve();

        let html = '';
        if (resolved.length === 0) {
          widget.querySelector('.widget-no-items-found').classList.remove('hide');
          widget.querySelector('thead').classList.add('hide');
        }
        resolved.forEach(function (item) {
          let comments = '';
          if (item.data.tx_ximatypo3contentplanner_comments > 0) {
            comments = '<a href="' + TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments  + '" class="contentPlanner--comments" data-table="' + item.data.tablename + '" data-id="' + item.data.uid + '">' + item.comments + '</a>';
            if (item.todo !== '') {
              comments += ' <a href="' + TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments  + '" class="contentPlanner--comments" data-table="' + item.data.tablename + '" data-id="' + item.data.uid + '">' + item.todo + '</a>';
            }
          }

          // ToDo: refactor this
          html += '<tr ' + (item.assignedToCurrentUser ? 'class="current"' : '') + '>' +
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
          waitingElement.classList.add('hide');
        }
        widget.querySelector('.widget-table-wrapper').classList.remove('hide');

        widget.querySelectorAll('.contentPlanner--comments').forEach(item => {
          item.addEventListener('click', e => {
            e.preventDefault();
            const url = e.currentTarget.getAttribute('href');
            const table = e.currentTarget.getAttribute('data-table');
            const id = e.currentTarget.getAttribute('data-id');
            CommentsModal.fetchComments(url, table, id);
          });
        });
      });
  }
}

export default new FilterStatus();
