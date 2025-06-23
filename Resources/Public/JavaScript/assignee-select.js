/**
* Module: @xima/ximatypo3contentplanner/assignee-select
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Viewport from "@typo3/backend/viewport.js"
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class AssigneeSelect {
  constructor() {
    window.addEventListener('typo3:contentplanner:reinitializelistener', (event) => {
      const modal = event.detail?.modal
      if (modal) {
        this.initEventListeners(modal)
        return
      }
      this.initEventListeners()
    })
  }

  initEventListeners(modal = null) {
    document.querySelector('[data-action-assignee-selection]').addEventListener('change', (event) => {
      event.preventDefault()
      const selectedOption = event.target.selectedOptions[0]
      // Workaround to check for unset assignee
      const hasDoubleDash = selectedOption && typeof selectedOption.label === 'string' && selectedOption.label.includes('--')
      this.changeAssignee(event.target.value, hasDoubleDash, modal)
    })

    document.querySelectorAll('[data-action-assignee]').forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault()
        this.changeAssignee(event.currentTarget.getAttribute('href'), event.currentTarget.hasAttribute('data-action-assignee-unset'), modal)
      })
    })
  }

  changeAssignee(url, unset = false, modal = null) {
    new AjaxRequest(url)
      .get()
      .then(async result => {
        if (result.response.ok === true) {
          if (modal) {
            modal.hideModal()
          }

          Viewport.ContentContainer.refresh()
        } else {
          console.error('Failed to change assignee:', result)
        }
        Notification.message(
          unset ? 'assignee.reset' : 'assignee.changed',
          result.response.ok ? "success" : "failure"
        )
      })
  }
}

export default new AssigneeSelect()
