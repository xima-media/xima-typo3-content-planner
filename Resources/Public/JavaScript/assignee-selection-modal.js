/**
* Module: @xima/ximatypo3contentplanner/assignee-selection-modal
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Modal from "@typo3/backend/modal.js"

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
    const buttons = [
      {
        text: TYPO3.lang?.['button.modal.footer.close'] || 'Close',
        name: 'close',
        icon: 'actions-close',
        active: true,
        btnClass: 'btn-secondary',
        trigger: (event, modal) => modal.hideModal()
      }
    ]

    new AjaxRequest(url)
      .withQueryArguments({table, uid, currentAssignee})
      .get()
      .then(async response => {
        const resolved = await response.resolve()
        Modal.advanced({
          title: TYPO3.lang?.['button.modal.header.assignee'] || 'Assignee',
          content: document.createRange().createContextualFragment(resolved.result),
          size: Modal.sizes.small,
          staticBackdrop: true,
          buttons,
          callback: (modal) => {
            // Reinitialize the modal after a short delay to ensure all elements are loaded
            setTimeout(() => {
              modal.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {
                bubbles: true,
                composed: true,
                detail: { modal }
              }))
            }, 700)
          }
        })
      })
  }
}

export default new AssigneeSelectionModal()
