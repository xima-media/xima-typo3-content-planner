/**
* Module: @content-planner/watch-toggle
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Notification from "@content-planner/notification.js"

class WatchToggle {

  constructor() {
    // Delegated on document: the toggle re-renders itself (container.outerHTML) on every click,
    // so a listener bound directly to the button would be gone after the first toggle.
    document.addEventListener('click', (event) => {
      if (!(event.target instanceof Element)) {
        return
      }
      const toggle = event.target.closest('[data-content-planner-watch-toggle]')
      if (!toggle || toggle.disabled) {
        return
      }
      event.preventDefault()
      this.toggle(toggle)
    })
  }

  toggle(toggle) {
    const table = toggle.getAttribute('data-table')
    const uid = toggle.getAttribute('data-uid')
    if (!table || !uid) {
      console.warn('Missing parameters for watch toggle:', {table, uid})
      return
    }

    const container = toggle.closest('[data-content-planner-watch-container]')
    // Guard against a second click landing before the first request's DOM swap - without this,
    // an out-of-order response could leave the rendered markup behind the actual DB state.
    toggle.disabled = true

    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_watch_toggle)
      .withQueryArguments({table, uid})
      .get()
      .then(async (response) => {
        const resolved = await response.resolve()
        if (container && resolved.result) {
          this.replaceAndRestoreFocus(container, resolved.result)
        }
      })
      .catch((error) => {
        toggle.disabled = false
        if ('AbortError' === error?.name) {
          // The request was likely aborted by a content frame refresh (e.g. Firefox
          // NS_BINDING_ABORTED) - same rationale as the other content-planner AJAX modules.
          console.debug('Content Planner: watch toggle request did not complete:', error)
          return
        }
        console.error('Failed to toggle watch state:', error)
        Notification.message('watch.toggle', 'failure')
      })
  }

  /**
   * `container.outerHTML = html` detaches the clicked button, so keyboard focus would otherwise
   * fall back to <body> with no indication the toggle succeeded. Capture a stable anchor before
   * the swap and refocus the re-rendered toggle button afterwards.
   */
  replaceAndRestoreFocus(container, html) {
    const parent = container.parentElement
    const nextSibling = container.nextSibling
    container.outerHTML = html

    const replaced = nextSibling ? nextSibling.previousSibling : parent?.lastElementChild
    replaced?.querySelector?.('[data-content-planner-watch-toggle]')?.focus()
  }
}

export default new WatchToggle()
