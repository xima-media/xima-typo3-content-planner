/**
* Module: @content-planner/assignee-select
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Viewport from "@typo3/backend/viewport.js"
import Notification from "@content-planner/notification.js";
import OptimisticUpdate from "@content-planner/optimistic-update.js";

const NAVIGATION_KEYS = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter']

/**
 * Searchable, keyboard-navigable replacement for the former native <select> assignee picker
 * (CP-26, #325). Every entry is a role="option" list item (avatar + name); none of them carry
 * an action URL as an <option> value, and selecting one only marks it as the pending choice -
 * the actual assignment is only submitted once the explicit "Confirm" button is activated.
 * "Assign to me" / "Unassign" stay distinct one-click actions, independent of the list's
 * pending selection.
 */
class AssigneeSelect {
  constructor() {
    window.addEventListener('typo3:contentplanner:reinitializelistener', (event) => {
      this.initEventListeners(event.detail?.modal || null)
    })
  }

  initEventListeners(modal = null) {
    const root = modal || document
    const listbox = root.querySelector('[data-assignee-listbox]')
    if (!listbox) {
      // Not (or no longer) the assignee modal content, e.g. the reinit event fired for
      // a different modal instance.
      return
    }

    const ctx = {
      modal,
      listbox,
      options: Array.from(listbox.querySelectorAll('[data-assignee-option]')),
      search: root.querySelector('[data-assignee-search]'),
      confirmButton: root.querySelector('[data-action-assignee-confirm]'),
      emptyState: root.querySelector('[data-assignee-empty]'),
      state: { activeIndex: 0, pendingUid: null },
    }

    const currentIndex = ctx.options.findIndex(option => 'true' === option.getAttribute('aria-current'))
    ctx.state.activeIndex = currentIndex >= 0 ? currentIndex : 0
    this.updateActiveDescendant(ctx)

    ctx.options.forEach(option => {
      option.addEventListener('click', () => this.selectOption(ctx, option))
    })

    listbox.addEventListener('keydown', event => this.handleListboxKeydown(ctx, event))

    if (ctx.search) {
      ctx.search.addEventListener('input', () => this.filterOptions(ctx))
      ctx.search.addEventListener('keydown', event => {
        if (NAVIGATION_KEYS.includes(event.key)) {
          this.handleListboxKeydown(ctx, event)
        }
      })
    }

    if (ctx.confirmButton) {
      ctx.confirmButton.addEventListener('click', () => this.confirmSelection(ctx))
    }

    root.querySelectorAll('[data-action-assignee]').forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault()
        this.changeAssignee(item.dataset.url, item.hasAttribute('data-action-assignee-unset'), modal)
      })
    })
  }

  // --- listbox navigation ----------------------------------------------------

  visibleOptions(ctx) {
    return ctx.options.filter(option => !option.hidden)
  }


  /**
   * Disables (or re-enables) the search field, listbox and action buttons of the open modal.
   * Returns the elements it touched so the caller can restore exactly those.
   *
   * @param {Element|Object|null} scope modal on disable, previously returned list on enable
   * @param {boolean} disabled
   * @returns {Element[]}
   */
  static setControlsDisabled(scope, disabled) {
    if (!disabled) {
      const previous = Array.isArray(scope) ? scope : []
      previous.forEach(el => {
        el.disabled = el.dataset.assigneePrevDisabled === 'true'
        delete el.dataset.assigneePrevDisabled
        el.removeAttribute('aria-disabled')
        if (el.dataset.assigneePrevTabindex === undefined) {
          el.removeAttribute('tabindex')
        } else {
          el.setAttribute('tabindex', el.dataset.assigneePrevTabindex)
          delete el.dataset.assigneePrevTabindex
        }
      })

      return []
    }

    const root = scope?.element ?? scope ?? document
    const elements = Array.from(
      root.querySelectorAll?.('[data-assignee-search], [data-assignee-listbox], [data-action-assignee-confirm], [data-action-assignee]') ?? [],
    )

    elements.forEach(el => {
      if ('disabled' in el) {
        el.dataset.assigneePrevDisabled = String(el.disabled)
        el.disabled = true
      }
      el.setAttribute('aria-disabled', 'true')
      if (el.hasAttribute('tabindex')) {
        el.dataset.assigneePrevTabindex = el.getAttribute('tabindex')
      }
      el.setAttribute('tabindex', '-1')
    })

    return elements
  }

  updateActiveDescendant(ctx) {
    const active = ctx.options[ctx.state.activeIndex]

    // aria-activedescendant is only announced on the element that actually has focus. Arrow
    // keys are forwarded from the search field, so pointing it at the listbox alone left
    // screen reader users with no idea which assignee was active while they were typing.
    const focusTarget = (ctx.search && document.activeElement === ctx.search) ? ctx.search : ctx.listbox
    const otherTarget = focusTarget === ctx.listbox ? ctx.search : ctx.listbox

    focusTarget.setAttribute('aria-activedescendant', active ? active.id : '')
    otherTarget?.removeAttribute('aria-activedescendant')
    ctx.options.forEach(option => option.classList.toggle('content-planner-assignee__item--active', option === active))
    if (active) {
      active.scrollIntoView({ block: 'nearest' })
    }
  }

  moveActive(ctx, delta) {
    const visible = this.visibleOptions(ctx)
    if (0 === visible.length) {
      return
    }
    const currentVisibleIndex = Math.max(visible.indexOf(ctx.options[ctx.state.activeIndex]), 0)
    const nextVisibleIndex = (currentVisibleIndex + delta + visible.length) % visible.length
    ctx.state.activeIndex = ctx.options.indexOf(visible[nextVisibleIndex])
    this.updateActiveDescendant(ctx)
  }

  moveActiveToEdge(ctx, edge) {
    const visible = this.visibleOptions(ctx)
    if (0 === visible.length) {
      return
    }
    const target = 'start' === edge ? visible[0] : visible[visible.length - 1]
    ctx.state.activeIndex = ctx.options.indexOf(target)
    this.updateActiveDescendant(ctx)
  }

  handleListboxKeydown(ctx, event) {
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        this.moveActive(ctx, 1)
        break
      case 'ArrowUp':
        event.preventDefault()
        this.moveActive(ctx, -1)
        break
      case 'Home':
        event.preventDefault()
        this.moveActiveToEdge(ctx, 'start')
        break
      case 'End':
        event.preventDefault()
        this.moveActiveToEdge(ctx, 'end')
        break
      case 'Enter':
      case ' ':
        event.preventDefault()
        if (ctx.options[ctx.state.activeIndex]) {
          this.selectOption(ctx, ctx.options[ctx.state.activeIndex])
        }
        break
      default:
        break
    }
  }

  filterOptions(ctx) {
    const term = ctx.search.value.trim().toLowerCase()
    let visibleCount = 0
    ctx.options.forEach(option => {
      const matches = '' === term || (option.dataset.assigneeName || '').toLowerCase().includes(term)
      option.hidden = !matches
      if (matches) {
        visibleCount++
      }
    })
    if (ctx.emptyState) {
      ctx.emptyState.hidden = visibleCount > 0
    }
    const visible = this.visibleOptions(ctx)
    if (visible.length > 0 && !visible.includes(ctx.options[ctx.state.activeIndex])) {
      ctx.state.activeIndex = ctx.options.indexOf(visible[0])
    }
    this.updateActiveDescendant(ctx)
  }

  // --- selection & submission -------------------------------------------------

  selectOption(ctx, option) {
    if (option.hasAttribute('aria-disabled')) {
      return
    }
    ctx.options.forEach(entry => entry.setAttribute('aria-selected', String(entry === option)))
    ctx.state.pendingUid = option.dataset.assigneeUid
    ctx.state.activeIndex = ctx.options.indexOf(option)
    if (ctx.confirmButton) {
      ctx.confirmButton.disabled = 'true' === option.getAttribute('aria-current')
    }
  }

  confirmSelection(ctx) {
    const option = ctx.options.find(entry => entry.dataset.assigneeUid === ctx.state.pendingUid)
    if (!option || !option.dataset.assigneeUrl) {
      return
    }
    this.changeAssignee(option.dataset.assigneeUrl, '0' === option.dataset.assigneeUid, ctx.modal, option)
  }

  changeAssignee(url, unset = false, modal = null, option = null) {
    if (!url) {
      return
    }

    // The pending CSS only suppresses pointer events, so a keyboard user could still confirm
    // a second assignment while the first request was in flight. Guard the request itself and
    // take the interactive controls out of the tab order for the duration.
    if (AssigneeSelect.requestPending) {
      return
    }
    AssigneeSelect.requestPending = true
    const controls = AssigneeSelect.setControlsDisabled(modal, true)

    OptimisticUpdate.run({
      apply: () => this.applyOptimistically(option, modal),
      request: () => new AjaxRequest(url).get().then(result => {
        if (!result.response.ok) {
          throw new Error('Content Planner: assignee change request failed')
        }
        return result
      }),
      rollback: snapshot => this.rollback(snapshot),
    }).finally(() => {
      AssigneeSelect.requestPending = false
      AssigneeSelect.setControlsDisabled(controls, false)
    }).then(() => {
      if (modal) {
        modal.hideModal()
      }
      Viewport.ContentContainer.refresh()
      Notification.message(unset ? 'assignee.reset' : 'assignee.changed', 'success')
    }).catch(() => {
      Notification.message(unset ? 'assignee.reset' : 'assignee.changed', 'failure')
    })
  }

  /**
   * Disables the modal's interactive controls immediately (avoids double submits while the
   * request is in flight) and, for a list selection, marks the chosen entry as current right
   * away. Returns a snapshot for rollback() to undo both on failure.
   */
  applyOptimistically(option, modal) {
    const root = modal || document
    const container = root.querySelector('[data-assignee-selector]')
    if (container) {
      container.classList.add('content-planner-assignee--pending')
      container.setAttribute('aria-busy', 'true')
    }

    if (!option) {
      return { container, previousCurrent: null, option: null }
    }

    const previousCurrent = root.querySelector('[data-assignee-option][aria-current="true"]')
    if (previousCurrent && previousCurrent !== option) {
      previousCurrent.removeAttribute('aria-current')
    }
    option.setAttribute('aria-current', 'true')

    return { container, previousCurrent, option }
  }

  rollback(snapshot) {
    if (!snapshot) {
      return
    }
    if (snapshot.container) {
      snapshot.container.classList.remove('content-planner-assignee--pending')
      snapshot.container.removeAttribute('aria-busy')
    }
    if (snapshot.option) {
      snapshot.option.removeAttribute('aria-current')
    }
    if (snapshot.previousCurrent) {
      snapshot.previousCurrent.setAttribute('aria-current', 'true')
    }
  }
}

export default new AssigneeSelect()
