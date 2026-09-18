/**
* Module: @content-planner/record-modal
*
* Shared modal shell for the Comments and Assignee record actions: a single `Modal.advanced()`
* instance with a "Comments" and an "Assignee" tab. TYPO3 core's modal API has no multi-view
* concept, and its real `.modal-header` is Lit-managed with markup that differs between v13 and
* v14 - the tab bar therefore lives inside the modal's own content fragment instead, at the top
* of the body, rather than being injected into that header.
*
* Each tab is fetched lazily on first activation and then only ever hidden/shown afterwards
* (never re-fetched or removed) for as long as the modal stays open - switching tabs must not
* lose an in-progress reply, a filter selection or a scroll position. This also means the global
* `typo3:contentplanner:reinitializelistener` event - several other modules re-bind their
* listeners on it without an idempotency guard - is dispatched at most once per pane, exactly
* matching the single dispatch the former, single-view Comments modal already did. The Assignee
* pane is initialised through a direct, scoped call instead of that global event, so a later
* Comments-pane dispatch can never re-trigger it a second time.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Modal from "@typo3/backend/modal.js"
import Icons from "@typo3/backend/icons.js"
import AssigneeSelect from "@content-planner/assignee-select.js"

const TAB_NAMES = ['comments', 'assignee']
const TABS = {
  comments: {
    icon: 'content-planner-message-circle',
    labelKey: 'button.modal.header.comments',
    labelFallback: 'Comments',
  },
  assignee: {
    icon: 'content-planner-user-circle',
    labelKey: 'button.modal.header.assignee',
    labelFallback: 'Assignee',
  },
}

class RecordModal {
  constructor() {
    this.reset()
  }

  reset() {
    this.modal = null
    this.recordKey = null
    this.context = null
    this.panes = {}
    this.tabButtons = {}
  }

  /**
   * @param {string} tab 'comments' | 'assignee' - the tab to show/activate
   * @param {Object} context
   * @param {string} context.table
   * @param {string|number} context.uid
   * @param {string} [context.commentsUrl] override for the comments AJAX endpoint
   * @param {string} [context.assigneeUrl] override for the assignee AJAX endpoint
   * @param {string|false} [context.editUri]
   * @param {string|number|false} [context.currentAssignee]
   * @param {string|null} [context.scrollToCommentUid]
   * @param {boolean} [context.showResolvedComments]
   * @param {boolean} [context.showTodoComments]
   * @param {boolean} [context.focusComposer]
   */
  open(tab, context) {
    const recordKey = `${context.table}:${context.uid}`
    if (this.modal && this.recordKey === recordKey) {
      this.activateTab(tab)
      return
    }

    this.reset()
    this.recordKey = recordKey
    this.context = context

    Modal.advanced({
      title: TYPO3.lang?.['modal.record.title'] || 'Content Planner',
      content: this.buildShell(tab),
      size: Modal.sizes.large,
      staticBackdrop: true,
      buttons: this.buildFooterButtons(),
      callback: (modal) => {
        this.modal = modal
        modal.addEventListener('typo3-modal-shown', () => this.activateTab(tab), { once: true })
        modal.addEventListener('typo3-modal-hide', () => this.reset(), { once: true })
      }
    })
  }

  buildShell(activeTab) {
    const wrapper = document.createElement('div')
    wrapper.className = 'content-planner-record-modal'

    const tablist = document.createElement('div')
    tablist.className = 'content-planner-record-modal__tabs'
    tablist.setAttribute('role', 'tablist')
    tablist.setAttribute('aria-label', TYPO3.lang?.['modal.tabs.label'] || 'Record actions')
    wrapper.append(tablist)

    TAB_NAMES.forEach(name => {
      const config = TABS[name]
      const isActive = name === activeTab

      const button = document.createElement('button')
      button.type = 'button'
      button.className = 'content-planner-record-modal__tab'
      button.id = `content-planner-tab-${name}`
      button.setAttribute('role', 'tab')
      button.setAttribute('aria-controls', `content-planner-pane-${name}`)
      button.setAttribute('aria-selected', String(isActive))
      button.setAttribute('tabindex', isActive ? '0' : '-1')

      // Not <typo3-backend-icon>: that custom element fetches its SVG lazily on its own
      // first Lit update, which never runs here - the whole tab bar is built as a detached
      // subtree and only gets connected to the document once Modal.advanced() has finished
      // its own async setup, by which point the icon element's one connection-triggered
      // update has already been missed. Icons.getIcon() is the same fetch (and cache) the
      // custom element uses internally, just applied directly once resolved.
      const icon = document.createElement('span')
      icon.className = 'content-planner-record-modal__tab-icon'
      Icons.getIcon(config.icon, 'small').then(markup => { icon.innerHTML = markup })
      button.append(icon)

      const label = document.createElement('span')
      label.textContent = TYPO3.lang?.[config.labelKey] || config.labelFallback
      button.append(label)

      button.addEventListener('click', () => this.activateTab(name))
      button.addEventListener('keydown', event => this.handleTabKeydown(event, name))

      tablist.append(button)
      this.tabButtons[name] = button

      const pane = document.createElement('div')
      pane.className = 'content-planner-record-modal__pane'
      pane.id = `content-planner-pane-${name}`
      pane.setAttribute('role', 'tabpanel')
      pane.setAttribute('aria-labelledby', button.id)
      pane.setAttribute('tabindex', '0')
      pane.hidden = !isActive
      wrapper.append(pane)
      this.panes[name] = pane
    })

    return wrapper
  }

  buildFooterButtons() {
    const buttons = []

    if (this.context.editUri) {
      buttons.push({
        text: TYPO3.lang?.['button.modal.footer.edit'] || 'Edit',
        name: 'edit',
        icon: 'actions-flag-edit',
        active: true,
        btnClass: 'btn-secondary',
        trigger: (event, modal) => {
          modal.hideModal()
          setTimeout(() => window.location.href = this.context.editUri, 100)
        }
      })
    }

    buttons.push({
      text: TYPO3.lang?.['button.modal.footer.close'] || 'Close',
      name: 'close',
      icon: 'actions-close',
      active: true,
      btnClass: 'btn-secondary',
      trigger: (event, modal) => modal.hideModal()
    })

    return buttons
  }

  handleTabKeydown(event, name) {
    const currentIndex = TAB_NAMES.indexOf(name)
    let targetIndex

    switch (event.key) {
      case 'ArrowRight':
        targetIndex = (currentIndex + 1) % TAB_NAMES.length
        break
      case 'ArrowLeft':
        targetIndex = (currentIndex - 1 + TAB_NAMES.length) % TAB_NAMES.length
        break
      case 'Home':
        targetIndex = 0
        break
      case 'End':
        targetIndex = TAB_NAMES.length - 1
        break
      default:
        return
    }

    event.preventDefault()
    const target = TAB_NAMES[targetIndex]
    this.activateTab(target)
    this.tabButtons[target].focus()
  }

  activateTab(tab) {
    if (!this.modal || !TABS[tab]) {
      return
    }

    TAB_NAMES.forEach(name => {
      const isActive = name === tab
      this.tabButtons[name].setAttribute('aria-selected', String(isActive))
      this.tabButtons[name].setAttribute('tabindex', isActive ? '0' : '-1')
      this.panes[name].hidden = !isActive
    })

    this.loadPaneIfNeeded(tab)
  }

  loadPaneIfNeeded(tab) {
    const pane = this.panes[tab]
    if (pane.dataset.loaded) {
      return
    }
    pane.dataset.loaded = 'pending'

    this.fetchTabContent(tab)
      .then(payload => {
        // The modal was closed (reset()) or reopened for a different record while this
        // request was in flight - this.panes[tab] now points at a different element.
        if (this.panes[tab] !== pane) {
          return
        }
        pane.dataset.loaded = 'true'
        pane.appendChild(document.createRange().createContextualFragment(payload.result))
        this.onPaneReady(tab, pane, payload)
      })
      .catch(error => {
        console.error(`Content Planner: failed to load the "${tab}" tab:`, error)
        delete pane.dataset.loaded
      })
  }

  fetchTabContent(tab) {
    const { table, uid } = this.context

    if ('assignee' === tab) {
      const url = this.context.assigneeUrl || TYPO3.settings.ajaxUrls.ximatypo3contentplanner_assignees
      const queryArguments = { table, uid }
      if (undefined !== this.context.currentAssignee && false !== this.context.currentAssignee) {
        queryArguments.currentAssignee = this.context.currentAssignee
      }

      return new AjaxRequest(url)
        .withQueryArguments(queryArguments)
        .get()
        .then(async response => response.resolve())
    }

    const url = this.context.commentsUrl || TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
    const queryArguments = { table, uid }
    if (this.context.showResolvedComments) {
      queryArguments.showResolvedComments = 1
    }
    if (this.context.showTodoComments) {
      queryArguments.showTodoComments = 1
    }

    return new AjaxRequest(url)
      .withQueryArguments(queryArguments)
      .get()
      .then(async response => response.resolve())
  }

  onPaneReady(tab, pane, payload) {
    if ('assignee' === tab) {
      AssigneeSelect.initEventListeners(this.modal, pane)
      return
    }

    this.updateTabCount('comments', payload.commentsCount)

    this.modal.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {
      bubbles: true,
      composed: true,
      detail: { modal: this.modal }
    }))

    if (this.context.scrollToCommentUid) {
      this.scrollToComment(pane, this.context.scrollToCommentUid)
    } else if (this.context.focusComposer) {
      const composerTrigger = pane.querySelector('[data-comment-composer-trigger]')
      composerTrigger?.scrollIntoView({behavior: 'smooth', block: 'center'})
      composerTrigger?.click()
    }
  }

  updateTabCount(tab, count) {
    if (undefined === count || null === count) {
      return
    }

    let badge = this.tabButtons[tab].querySelector('.content-planner-record-modal__tab-count')
    if (!badge) {
      badge = document.createElement('span')
      badge.className = 'content-planner-record-modal__tab-count'
      this.tabButtons[tab].append(badge)
    }
    badge.textContent = String(count)
    badge.hidden = 0 === count
  }

  scrollToComment(pane, commentUid) {
    setTimeout(() => {
      // Expand all collapsed reply sections so the target comment is visible
      pane.querySelectorAll('.content-planner-comment-replies.collapse:not(.show)').forEach(el => {
        el.classList.add('show')
        const toggle = pane.querySelector(`[aria-controls="${CSS.escape(el.id)}"]`)
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'true')
        }
      })

      setTimeout(() => {
        const commentElement = pane.querySelector(`[data-comment-uid="${CSS.escape(commentUid)}"]`)
        if (commentElement) {
          commentElement.scrollIntoView({behavior: 'smooth', block: 'center'})
          commentElement.classList.add('content-planner-comment--highlight')
          setTimeout(() => {
            commentElement.classList.remove('content-planner-comment--highlight')
          }, 2500)
        }
      }, 100)
    }, 300)
  }
}

export default new RecordModal()
