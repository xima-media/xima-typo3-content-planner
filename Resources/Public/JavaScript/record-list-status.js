/**
* Module: @xima/ximatypo3contentplanner/record-list-status
*
* Handles status changes in record list dropdowns via AJAX
* to ensure proper page refresh after status change.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Viewport from "@typo3/backend/viewport.js"
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class RecordListStatus {
  constructor() {
    this.initEventListeners()

    // Re-initialize after page content changes
    document.addEventListener('typo3-module-loaded', () => {
      this.initEventListeners()
    })
  }

  initEventListeners() {
    document.querySelectorAll('[data-content-planner-status-change]').forEach(item => {
      if (item.hasAttribute('data-content-planner-status-initialized')) {
        return
      }
      item.setAttribute('data-content-planner-status-initialized', 'true')

      item.addEventListener('click', event => {
        event.preventDefault()
        const href = item.getAttribute('href')
        const isReset = item.hasAttribute('data-content-planner-status-reset')
        this.changeStatus(href, isReset)
      })
    })
  }

  changeStatus(url, isReset = false) {
    new AjaxRequest(url)
      .get()
      .then(async result => {
        Notification.message(
          isReset ? 'status.reset' : 'status.changed',
          result.response.ok ? "success" : "failure"
        )

        if (result.response.ok === true) {
          top.document.dispatchEvent(new CustomEvent("typo3:pagetree:refresh"))
          top.document.dispatchEvent(new CustomEvent("typo3:filestoragetree:refresh"))
          Viewport.ContentContainer.refresh()
        }
      })
      .catch(error => {
        console.error('Failed to change status:', error)
        Notification.message('status.changed', 'failure')
      })
  }
}

export default new RecordListStatus()
