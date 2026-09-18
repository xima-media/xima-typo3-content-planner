/**
* Module: @content-planner/assignee-selection-modal
*/
import RecordModal from "@content-planner/record-modal.js"

class AssigneeSelectionModal {

  constructor() {
    document.querySelectorAll('[data-content-planner-assignees]').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault()
        const url = item.getAttribute('href') && !item.hasAttribute('data-force-ajax-url')
          ? item.getAttribute('href')
          : TYPO3.settings.ajaxUrls.ximatypo3contentplanner_assignees
        this.fetchUsers(
          url,
          item.getAttribute('data-table'),
          item.getAttribute('data-id'),
          item.getAttribute('data-current-assignee'),
        )
      })
    })
  }

  fetchUsers(url, table, uid, currentAssignee = false) {
    RecordModal.open('assignee', {
      table,
      uid,
      assigneeUrl: url,
      currentAssignee,
    })
  }
}

export default new AssigneeSelectionModal()
