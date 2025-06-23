/**
* Module: @xima/ximatypo3contentplanner/assignee-select
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Viewport from "@typo3/backend/viewport.js"

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
      this.changeAssignee(event.target.value, modal)
    })

    document.querySelectorAll('[data-action-assignee]').forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault()
        this.changeAssignee(event.currentTarget.getAttribute('href'), modal)
      })
    })
  }

  changeAssignee(url, modal = null) {
    new AjaxRequest(url)
      .get()
      .then(async result => {
        if (result.response.ok === true) {
          if (modal) {
            modal.hideModal()
          }

          Viewport.ContentContainer.refresh()
        } else {
          console.error('Failed to change assignee:', response)
        }
      })
  }
}

export default new AssigneeSelect()
