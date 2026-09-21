/**
* Module: @content-planner/header-tooltips
*
* Upgrades the header meta group's buttons from the plain browser `title` tooltip to a
* small floating one that can actually explain the watch toggle's four states (issue #404).
* Ported from EXT:typo3_page_metrics's `showHintTooltip`/`bindHintTooltip` (sparkline.js),
* which solves the exact same problem: the native title attribute waits about a second,
* cannot be styled to match the backend, and runs a name and its explanation into one line.
*
* Deliberately not a Bootstrap component: the bundled Contrib/bootstrap.js only exports
* Carousel, Collapse and Popover, not Tooltip, and Popover's own click-toggle default (plus
* its header/body split needing awkward `title`-stripping to avoid a duplicated line) is the
* wrong shape for a plain hover explanation anyway.
*/

const TRIGGER_SELECTOR = '.content-planner-meta__btn[data-hint-title]'

let tooltip = null
let tooltipFor = null

/**
 * One element on <body>, reused for every button - not one per trigger, and not appended
 * inside the header: `.content-planner-header__actions` and the doc header toolbar both clip
 * overflowing children, so anything sized to its content and anchored INSIDE either would be
 * cut off before it could show a multi-line explanation.
 */
function showTooltip(trigger, title, text) {
  if (!tooltip) {
    tooltip = document.createElement('div')
    tooltip.className = 'content-planner-header-tooltip'
    tooltip.setAttribute('aria-hidden', 'true')
    document.body.appendChild(tooltip)
  }

  tooltip.replaceChildren()

  const titleEl = document.createElement('span')
  titleEl.className = 'content-planner-header-tooltip__title'

  // Cloned from the button rather than looked up again here: the icon already sits next to
  // the thing being explained, so the tooltip and its subject read as one control.
  const icon = trigger.querySelector('svg')
  if (icon) {
    const iconWrapper = document.createElement('span')
    iconWrapper.setAttribute('aria-hidden', 'true')
    iconWrapper.appendChild(icon.cloneNode(true))
    titleEl.appendChild(iconWrapper)
  }

  titleEl.appendChild(document.createTextNode(title))
  tooltip.appendChild(titleEl)

  if ('' !== text) {
    const textEl = document.createElement('span')
    textEl.textContent = text
    tooltip.appendChild(textEl)
  }

  tooltip.hidden = false
  tooltipFor = trigger

  const rect = trigger.getBoundingClientRect()
  const margin = 8
  // Below by default - these buttons sit at the top of their own header, so there is rarely
  // room above them - and clamped inside the viewport horizontally.
  const width = tooltip.offsetWidth
  const below = rect.bottom + 4
  const fitsBelow = below + tooltip.offsetHeight < window.innerHeight - margin
  tooltip.style.top = `${fitsBelow ? below : rect.top - tooltip.offsetHeight - 4}px`
  tooltip.style.left = `${Math.min(Math.max(margin, rect.left + rect.width / 2 - width / 2), window.innerWidth - width - margin)}px`
}

function hideTooltip() {
  if (tooltip) {
    tooltip.hidden = true
    tooltipFor = null
  }
}

class HeaderTooltips {
  constructor() {
    // Delegated on document, not bound per element: the assignee, comments, todo and watch
    // buttons are all individually replaced via outerHTML on their own AJAX round trips (see
    // watch-toggle.js), so anything bound upfront would be orphaned the moment its button is
    // replaced. Binding lazily on first hover/focus instead means a fresh replacement node
    // just gets its own listeners the next time it is touched.
    //
    // Duck-typed target check rather than `instanceof Element`: this module, like watch-
    // toggle.js, is only ever imported once per document realm - a same-realm `Element`
    // constructor check fails for a target from a DIFFERENT realm (e.g. the record modal,
    // which TYPO3's Modal.advanced() attaches to top.document regardless of which realm
    // opened it), silently dropping every event from there.
    document.addEventListener('mouseover', event => {
      if ('function' === typeof event.target?.closest) {
        this.prepare(event.target.closest(TRIGGER_SELECTOR))
      }
    })
    document.addEventListener('focusin', event => {
      if ('function' === typeof event.target?.closest) {
        this.prepare(event.target.closest(TRIGGER_SELECTOR))
      }
    })

    // WCAG 2.1 SC 1.4.13 (Content on Hover or Focus): dismissable without moving the pointer
    // or focus. mouseleave/blur already cover pointer/keyboard navigating away from the
    // trigger; these two cover the remaining cases - the user wants it gone without doing
    // either (Escape), or the trigger scrolls out of view while the tooltip, anchored by a
    // one-time getBoundingClientRect() call, stays fixed at its now-stale position.
    document.addEventListener('keydown', event => {
      if ('Escape' === event.key) {
        hideTooltip()
      }
    })
    document.addEventListener('scroll', hideTooltip, true)
  }

  prepare(trigger) {
    if (!trigger || 'true' === trigger.dataset.headerTooltipBound) {
      return
    }
    trigger.dataset.headerTooltipBound = 'true'

    // The text is read from the dataset when the tooltip opens, not captured here: the watch
    // toggle's own explanation changes on every click, and capturing it now would freeze it at
    // whatever it said when this button was first hovered.
    const open = () => showTooltip(trigger, trigger.dataset.hintTitle || '', trigger.dataset.hintText || '')

    trigger.addEventListener('mouseenter', open)
    trigger.addEventListener('mouseleave', hideTooltip)
    trigger.addEventListener('focus', open)
    trigger.addEventListener('blur', hideTooltip)

    // The hover or focus that got us here happened before these listeners existed, so
    // neither one saw it - the first tooltip has to be opened by hand.
    open()
  }

  /**
   * The tooltip is one shared element, not an instance per trigger, so there is nothing to
   * disassemble - only to hide if it happens to be the one currently open. A button replaced
   * via outerHTML (see watch-toggle.js) while its tooltip is visible would otherwise leave it
   * floating over the page, anchored to a position nothing will update again.
   */
  hideIfShownFor(trigger) {
    if (tooltipFor === trigger) {
      hideTooltip()
    }
  }
}

export default new HeaderTooltips()
