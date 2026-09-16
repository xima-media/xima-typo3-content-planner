/**
* Module: @content-planner/comment-mention-card
*
* Profile card behind a rendered @-mention (#305). MentionUtility renders every marker as a
* `<button class="ctp-mention" data-mention-uid="42">`; this turns that button into a popover
* showing who the person is.
*
* Deliberately not a link: `be_users` is an adminOnly table, so the `record_edit` link the
* markers used to carry was a dead end for everyone but administrators. The card is fetched on
* first hover or focus rather than rendered into the comment HTML, so contact details never sit
* on the page unless somebody actually asks for them.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Popover from "@typo3/backend/popover.js"

const TRIGGER_SELECTOR = '.ctp-mention[data-mention-uid]'

class CommentMentionCard {
  constructor() {
    this.cache = new Map()

    // Delegated on the document: comment markup is replaced wholesale on every filter change
    // and after every save, so anything bound to the individual buttons would need rebinding
    // on each reload.
    document.addEventListener('mouseover', event => this.prepare(event.target.closest(TRIGGER_SELECTOR)))
    document.addEventListener('focusin', event => this.prepare(event.target.closest(TRIGGER_SELECTOR)))
  }

  prepare(trigger) {
    if (!trigger || 'true' === trigger.dataset.mentionCardBound) {
      return
    }
    trigger.dataset.mentionCardBound = 'true'

    // Bootstrap reads the trigger mode when the instance is constructed, so it has to be in
    // place before Popover.popover() - setting it afterwards would not rebind its listeners.
    trigger.dataset.bsToggle = 'popover'
    trigger.dataset.bsTrigger = 'hover focus'

    Popover.popover(trigger)
    Popover.setOptions(trigger, this.options(trigger, TYPO3.lang?.['mention.card.loading'] || '…'))
    // The hover or focus that got us here happened before the instance existed, so Bootstrap
    // did not see it - the first card has to be opened by hand.
    Popover.show(trigger)

    this.load(trigger.dataset.mentionUid)
      .then(content => Popover.setOptions(trigger, this.options(trigger, content)))
      .catch(error => {
        console.error('Failed to load mention profile card:', error)
        Popover.hide(trigger)
      })
  }

  options(trigger, content) {
    return {
      content,
      html: true,
      customClass: 'content-planner-mention-card-popover',
      // Bootstrap appends the tip to `document.body`, which in TYPO3 v14 is painted below the
      // modal: it is opened as a native `<dialog>` via `showModal()` and therefore lives in the
      // browser's top layer, where no z-index from the outside can reach. Anchoring the tip in
      // the dialog itself puts it in the same top-layer subtree. In v13 there is no `<dialog>`
      // ancestor and this is the Bootstrap default.
      container: trigger.closest('dialog') || document.body,
      // The card is our own Fluid-rendered fragment and every value in it is escaped on the way
      // out; Bootstrap's sanitizer would strip the `style` attribute and the icon `<svg>` that
      // TYPO3's avatar markup is built from and leave a misshapen card behind.
      sanitize: false,
    }
  }

  /**
   * @returns {Promise<string>}
   */
  async load(uid) {
    if (this.cache.has(uid)) {
      return this.cache.get(uid)
    }

    const request = new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_mentions_profile)
      .withQueryArguments({uid})
      .get()
      .then(async response => (await response.resolve()).result)

    // Cached as the pending promise, not the resolved value: the same person is usually
    // mentioned more than once in a thread, and hovering two of those in quick succession
    // should not fire the request twice. A failure drops out of the cache again so the next
    // hover retries instead of replaying the rejection forever.
    this.cache.set(uid, request)
    request.catch(() => this.cache.delete(uid))

    return request
  }
}

export default new CommentMentionCard()
