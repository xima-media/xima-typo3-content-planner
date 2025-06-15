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
      }
      this.initEventListeners()
    })
  }

  initEventListeners(modal = null) {
    document.querySelector('[data-assignee-selection]').addEventListener('change', (event) => {
      event.preventDefault()
      const selectElement = event.target

      new AjaxRequest(selectElement.value).get()

      if (modal) {
        modal.hideModal()
      }
      Viewport.ContentContainer.refresh()
    })

    document.querySelectorAll('[data-action-assignee]').forEach(item => {
      item.addEventListener('click', (event) => {
        event.preventDefault()
        const selectElement = event.target

        new AjaxRequest(selectElement.getAttribute('href')).get()

        if (modal) {
          modal.hideModal()
        }
        Viewport.ContentContainer.refresh()
      })
    })
  }
}

export default new AssigneeSelect()
