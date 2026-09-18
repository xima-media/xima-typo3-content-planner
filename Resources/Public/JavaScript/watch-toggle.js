/**
* Module: @content-planner/watch-toggle
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Notification from "@content-planner/notification.js"
import HeaderTooltips from "@content-planner/header-tooltips.js"

class WatchToggle {

  constructor() {
    // Delegated rather than bound to individual buttons: the toggle re-renders itself
    // (outerHTML) on every click, so a direct listener would be gone after the first toggle.
    //
    // Bound on both this module's own document AND top.document: this module is only ever
    // imported once per document realm (the page module iframe's, via loadHeaderAssets()), so
    // a plain `document` listener only ever sees clicks inside that iframe. The record modal's
    // Watch tab renders the same toggle a second time, but TYPO3's Modal.advanced() attaches
    // the dialog to the TOP window's document so it can overlay the whole backend, not just
    // the iframe - clicks there would otherwise never reach this listener at all. Guarded so a
    // page that already IS the top window (no iframe involved) doesn't bind the same document
    // twice, which would double-toggle on every click.
    const documents = top !== window ? [document, top.document] : [document]
    documents.forEach(doc => this.bindClickDelegate(doc))
  }

  bindClickDelegate(doc) {
    doc.addEventListener('click', (event) => {
      // Duck-typed rather than `event.target instanceof Element`: this module is only ever
      // imported once per document realm (see the constructor), so `Element` here is THIS
      // realm's constructor. A click on the modal copy (top.document) hands this same-realm
      // listener a top-realm Element instance - cross-realm instanceof checks are always
      // false for otherwise-identical classes, so that check would silently reject every
      // click on whichever instance isn't in this module's own realm.
      if ('function' !== typeof event.target?.closest) {
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

    // The same record's toggle can render twice at once - the header and the record modal's
    // Watch tab both show it - so every matching instance (not just the one clicked) has to be
    // disabled and, on success, replaced, or the other one would silently go stale.
    const instances = WatchToggle.findInstances(table, uid)
    instances.forEach(instance => { instance.disabled = true })

    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_watch_toggle)
      .withQueryArguments({table, uid})
      .get()
      .then(async (response) => {
        const resolved = await response.resolve()
        if (resolved.result) {
          this.replaceAndRestoreFocus(toggle, WatchToggle.findInstances(table, uid), resolved.result)
        }
        // The record modal's Watch tab, if open on this exact record, shows the watcher count
        // and list as of when its pane was loaded - both go stale the moment anyone (in either
        // document, see the constructor) toggles watching, so it needs telling to refresh
        // itself. Not solved by replaceAndRestoreFocus() above: that only replaces the toggle
        // button, not the pane's surrounding heading/list content.
        WatchToggle.announceToggled(table, uid)
      })
      .catch((error) => {
        instances.forEach(instance => { instance.disabled = false })
        if (WatchToggle.isAbortedRequest(error)) {
          // The request was likely aborted by a content frame refresh - same rationale as the
          // other content-planner AJAX modules.
          console.debug('Content Planner: watch toggle request did not complete:', error)
          return
        }
        console.error('Failed to toggle watch state:', error)
        Notification.message('watch.toggle', 'failure')
      })
  }

  /**
   * Dispatched on top.document alone - unlike the click delegation in the constructor, this
   * only needs ONE listener to ever see it, so it uses top.document as a single canonical
   * event bus reachable from any realm (the iframe's top IS top.document; the top window's own
   * document already IS top.document). Dispatching on both documents, the way the constructor
   * listens on both, would fire every listener bound to either one twice over.
   */
  static announceToggled(table, uid) {
    top.document.dispatchEvent(new CustomEvent('content-planner:watch-toggled', {
      detail: {table, uid},
    }))
  }

  /**
   * Searches both this document and top.document (see the constructor) for the same reason:
   * a click landing on either one still has to keep every instance of this record's toggle -
   * header and modal alike - in sync.
   *
   * @returns {HTMLElement[]}
   */
  static findInstances(table, uid) {
    const selector = `[data-content-planner-watch-toggle][data-table="${CSS.escape(table)}"][data-uid="${CSS.escape(uid)}"]`
    const documents = top !== window ? [document, top.document] : [document]

    return documents.flatMap(doc => Array.from(doc.querySelectorAll(selector)))
  }

  /**
   * `container.outerHTML = html` detaches the clicked button, so keyboard focus would otherwise
   * fall back to <body> with no indication the toggle succeeded. The container is the toggle
   * button itself since it became a segment of the header's shared button group (issue #404),
   * so the re-rendered button is found through the surviving parent, not through the detached
   * node. Every instance (header + record modal Watch tab, if both are present) is replaced
   * with the same freshly rendered markup, but focus is only ever restored to the one the user
   * actually clicked.
   *
   * @param {HTMLElement} clicked
   * @param {HTMLElement[]} instances
   * @param {string} html
   */
  replaceAndRestoreFocus(clicked, instances, html) {
    instances.forEach(instance => {
      const parent = instance.parentElement
      // Hides the tooltip if it is showing for this exact button at the moment of the swap -
      // the trigger it is anchored to is about to leave the document, and nothing else would
      // tell it to close or reposition.
      HeaderTooltips.hideIfShownFor(instance)
      const wasClicked = instance === clicked
      instance.outerHTML = html

      if (wasClicked) {
        parent?.querySelector?.('[data-content-planner-watch-toggle]')?.focus()
      }
    })
  }

  /**
   * A navigation or content-frame refresh cancels in-flight requests. Chromium reports that
   * as an AbortError, but Firefox surfaces NS_BINDING_ABORTED as a plain TypeError with a
   * network message - so matching on the name alone showed the user a failure toast for
   * something that never actually failed.
   *
   * @param {*} error
   * @returns {boolean}
   */
  static isAbortedRequest(error) {
    if ('AbortError' === error?.name) {
      return true
    }

    return error instanceof TypeError && /NetworkError|network error|Failed to fetch/i.test(error?.message ?? '')
  }
}

export default new WatchToggle()
