/**
* Module: @xima/ximatypo3contentplanner/filter-status
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Modal from "@typo3/backend/modal.js";
import Persistent from "@typo3/backend/storage/persistent.js";
import CommentsModal from "@xima/ximatypo3contentplanner/comments-list-modal.js";

class FilterStatus {

  static STORAGE_KEY = 'contentPlannerFilter';

  constructor() {
    document.addEventListener('widgetContentRendered', function(event) {
      if (event.target.querySelector('.content-planner-widget')) {
        const widget = event.target.querySelector('.content-planner-widget');

        // Skip server-rendered widgets (TYPO3 v14+ configurable widget)
        if (widget.classList.contains('content-planner-widget--server-rendered')) {
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
          widget.classList.add('content-planner-widget--assigned');
        } else if (todo && todo !== 'false') {
          FilterStatus.search(widget, {todo: true});
          widget.classList.add('content-planner-widget--todo');
        } else {
          let queryArguments = FilterStatus.loadFilter();
          const form = event.target.querySelector('.content-planner-widget__filter-form');
          const search = event.target.querySelector('input[name="search"]');
          const filterTrigger = event.target.querySelector('.content-planner-widget__filter-modal-trigger');
          const filterTemplate = event.target.querySelector('.content-planner-widget__filter-modal-content');

          // Restore search field value
          if (search && queryArguments.search) {
            search.value = queryArguments.search;
          }

          // Restore badge state
          if (filterTrigger) {
            FilterStatus.updateBadge(filterTrigger, queryArguments);
          }

          FilterStatus.search(widget, queryArguments);

          if (form && search) {
            search.addEventListener('input', function(event) {
              queryArguments[search.name] = search.value;
              FilterStatus.saveFilter(queryArguments);
              FilterStatus.search(widget, queryArguments);
            });

            if (filterTrigger && filterTemplate) {
              filterTrigger.addEventListener('click', function() {
                FilterStatus.openFilterModal(widget, filterTemplate, queryArguments, (newArgs) => {
                  queryArguments = newArgs;
                  FilterStatus.saveFilter(queryArguments);
                  FilterStatus.search(widget, queryArguments);
                  FilterStatus.updateBadge(filterTrigger, queryArguments);
                });
              });
            }

            form.querySelector('.content-planner-widget__filter-reset')?.addEventListener('click', function() {
              form.reset();
              queryArguments = {};
              FilterStatus.saveFilter(queryArguments);
              FilterStatus.search(widget, queryArguments);
              if (filterTrigger) {
                FilterStatus.updateBadge(filterTrigger, queryArguments);
              }
            });
          }
        }

      }
    });
  }

  static loadFilter() {
    try {
      const stored = Persistent.get(FilterStatus.STORAGE_KEY);
      if (typeof stored === 'object' && stored !== null) {
        return stored;
      }
      return stored ? JSON.parse(stored) : {};
    } catch {
      return {};
    }
  }

  static saveFilter(queryArguments) {
    const toStore = Object.fromEntries(
      Object.entries(queryArguments).filter(([, v]) => v)
    );
    if (Object.keys(toStore).length === 0) {
      Persistent.unset(FilterStatus.STORAGE_KEY);
    } else {
      Persistent.set(FilterStatus.STORAGE_KEY, toStore);
    }
  }

  static openFilterModal(widget, filterTemplate, currentArgs, onApply) {
    const content = filterTemplate.content.cloneNode(true);
    const container = document.createElement('div');
    container.appendChild(content);

    // Restore current filter values in the modal
    for (const [key, value] of Object.entries(currentArgs)) {
      if (key === 'search') continue;
      const element = container.querySelector(`[name="${key}"]`);
      if (element && element.type === 'checkbox') {
        element.checked = !!value;
      } else if (element) {
        element.value = value;
      }
    }

    // "Assign to me" shortcut button
    const assignToMeBtn = container.querySelector('.content-planner-widget__filter-assign-to-me');
    const assigneeSelect = container.querySelector('[name="assignee"]');
    if (assignToMeBtn && assigneeSelect) {
      assignToMeBtn.addEventListener('click', function() {
        assigneeSelect.value = assignToMeBtn.dataset.backendUserId;
      });
    }

    const buttons = [
      {
        text: TYPO3.lang?.['filter.modal.apply'] || 'Apply',
        name: 'apply',
        icon: 'actions-check',
        active: true,
        btnClass: 'btn-primary',
        trigger: (event, modal) => {
          const newArgs = { ...currentArgs };
          modal.querySelectorAll('.content-planner-widget__filter-modal-form select').forEach((select) => {
            if (select.value) {
              newArgs[select.name] = select.value;
            } else {
              delete newArgs[select.name];
            }
          });
          modal.querySelectorAll('.content-planner-widget__filter-modal-form input[type="checkbox"]').forEach((checkbox) => {
            if (checkbox.checked) {
              newArgs[checkbox.name] = checkbox.value;
            } else {
              delete newArgs[checkbox.name];
            }
          });
          onApply(newArgs);
          modal.hideModal();
        }
      },
      {
        text: TYPO3.lang?.['filter.reset'] || 'Reset',
        name: 'reset',
        icon: 'actions-close',
        active: true,
        btnClass: 'btn-default',
        trigger: (event, modal) => {
          const newArgs = {};
          if (currentArgs.search) {
            newArgs.search = currentArgs.search;
          }
          onApply(newArgs);
          modal.hideModal();
        }
      },
      {
        text: TYPO3.lang?.['button.modal.footer.close'] || 'Close',
        name: 'close',
        icon: 'actions-close',
        active: true,
        btnClass: 'btn-secondary',
        trigger: (event, modal) => modal.hideModal()
      }
    ];

    Modal.advanced({
      title: TYPO3.lang?.['filter.modal.title'] || 'Filter records',
      content: container,
      size: Modal.sizes.small,
      staticBackdrop: true,
      buttons,
    });
  }

  static updateBadge(filterTrigger, queryArguments) {
    const badge = filterTrigger.querySelector('.content-planner-widget__filter-badge');
    if (!badge) return;

    const activeCount = Object.keys(queryArguments).filter(key => key !== 'search' && queryArguments[key]).length;
    if (activeCount > 0) {
      badge.textContent = activeCount;
      badge.hidden = false;
    } else {
      badge.textContent = '';
      badge.hidden = true;
    }
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
    widget.querySelector('thead')?.classList.remove('content-planner-hide');
    widget.querySelector('.content-planner-widget__empty')?.classList.add('content-planner-hide');
    const waitingElement = widget.parentElement.parentElement.querySelector('.widget-waiting');
    if (waitingElement) {
      waitingElement.classList.remove('content-planner-hide');
    }
    widget.querySelector('.content-planner-widget__table-wrapper')?.classList.add('content-planner-hide');
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_filterrecords)
      .withQueryArguments(queryArguments)
      .get()
      .then(async (response) => {
        const resolved = await response.resolve();

        let html = '';
        if (resolved.length === 0) {
          widget.querySelector('.content-planner-widget__empty')?.classList.remove('content-planner-hide');
          widget.querySelector('thead')?.classList.add('content-planner-hide');
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
        if (table) {
          table.innerHTML = html;
        }
        if (waitingElement) {
          waitingElement.classList.add('content-planner-hide');
        }
        widget.querySelector('.content-planner-widget__table-wrapper')?.classList.remove('content-planner-hide');

        FilterStatus.initCommentLinks(widget);

        if (callback) {
          callback();
        }
      });
  }
}

export default new FilterStatus();
