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
import Notification from "@content-planner/notification.js"
import Viewport from "@typo3/backend/viewport.js"

const TAB_NAMES = ['comments', 'assignee', 'watch']
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
  watch: {
    icon: 'content-planner-bell',
    labelKey: 'button.modal.header.watch',
    labelFallback: 'Watchers',
  },
}

class RecordModal {
  constructor() {
    this.reset()

    // top.document alone, matching watch-toggle.js's own single dispatch target - see
    // WatchToggle.announceToggled()'s docblock for why only one of the two documents involved
    // is ever used as the event bus.
    top.document.addEventListener('content-planner:watch-toggled', event => {
      this.refreshWatchPaneIfOpen(event.detail.table, event.detail.uid)
    })
    top.document.addEventListener('content-planner:status-changed', event => {
      this.refreshStatus(event.detail.table, event.detail.uid)
    })
    top.document.addEventListener('content-planner:assignee-changed', event => {
      this.refreshAssigneePane()
    })
  }

  /** A comment sent together with a new status changed it: the status control follows, the page when the modal closes. */
  refreshStatus(table, uid) {
    if (!this.modal || this.recordKey !== `${table}:${uid}`) {
      return
    }
    this.fetchRecordInfo().then(info => {
      if (this.recordKey !== `${table}:${uid}`) {
        return
      }
      this.info = { ...this.info, status: info.status, statuses: info.statuses }
      this.renderStatusControl()
      this.pageStale = true
    }).catch(error => console.error('Content Planner: failed to reload the status:', error))
  }

  /**
   * The assignment is already saved when this runs. The Assignee tab is loaded again so it names
   * the new assignee, and the page behind the modal is refreshed once, when the modal closes.
   */
  refreshAssigneePane() {
    const pane = this.panes.assignee
    if (!this.modal || !pane) {
      return
    }

    this.pageStale = true
    // The value this modal was opened with is out of date, so the server reads the assignee itself.
    delete this.context.currentAssignee
    delete pane.dataset.loaded
    pane.replaceChildren()
    this.loadPaneIfNeeded('assignee')
  }

  /**
   * Re-fetches the Watch pane's content from the server so its watcher count/list stay
   * correct after a toggle - the toggle's own AJAX round trip (watch-toggle.js) only ever
   * replaces the toggle button itself, not the pane content around it.
   */
  refreshWatchPaneIfOpen(table, uid) {
    if (!this.modal || this.recordKey !== `${table}:${uid}`) {
      return
    }

    const pane = this.panes.watch
    if (!pane || !pane.dataset.loaded) {
      return
    }

    delete pane.dataset.loaded
    pane.replaceChildren()
    this.loadPaneIfNeeded('watch')
  }

  reset() {
    this.modal = null
    this.recordKey = null
    this.context = null
    this.info = null
    this.panes = {}
    this.tabButtons = {}
    this.pageStale = false
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

    // The title and the context row name the record, so the modal opens once the server has
    // described it. A failed request still opens the modal, only without that information.
    this.fetchRecordInfo()
      .catch(() => null)
      .then(async info => {
        await this.loadStylesheets(info?.stylesheets ?? [])
        if (this.recordKey !== recordKey) {
          return
        }
        this.info = info
        this.openModal(tab)
      })
  }

  /**
   * The modal frame is built before its first tab arrives, so the frame's stylesheet is loaded
   * up front. Otherwise the frame shows unstyled until the tab's own markup brings it along.
   */
  loadStylesheets(urls) {
    // This module may run in the content iframe, while Modal.advanced() attaches to the top document.
    const topDocument = window.top.document
    return Promise.all(urls.map(url => new Promise(resolve => {
      if (topDocument.head.querySelector(`link[rel="stylesheet"][href="${CSS.escape(url)}"]`)) {
        resolve()
        return
      }
      const link = topDocument.createElement('link')
      link.rel = 'stylesheet'
      link.href = url
      const timer = setTimeout(resolve, 3000)
      const settle = () => {
        clearTimeout(timer)
        resolve()
      }
      link.addEventListener('load', settle, { once: true })
      link.addEventListener('error', settle, { once: true })
      topDocument.head.append(link)
    })))
  }

  openModal(tab) {
    const recordTitle = TYPO3.lang?.['modal.record.title'] || 'Content Planner'

    Modal.advanced({
      title: this.info ? `${recordTitle}: ${this.info.title}` : recordTitle,
      content: this.buildShell(tab),
      size: Modal.sizes.large,
      staticBackdrop: true,
      buttons: this.buildFooterButtons(),
      callback: (modal) => {
        this.modal = modal
        modal.classList.add('content-planner-modal')
        modal.addEventListener('typo3-modal-shown', () => this.activateTab(tab), { once: true })
        modal.addEventListener('typo3-modal-hide', () => {
          const stale = this.pageStale
          this.reset()
          if (stale) {
            Viewport.ContentContainer.refresh()
          }
        }, { once: true })
      }
    })
  }

  fetchRecordInfo() {
    const { table, uid } = this.context

    return new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_modalcontext)
      .withQueryArguments({ table, uid })
      .get()
      .then(async response => {
        const resolved = await response.resolve()
        if (!response.response.ok || resolved.error) {
          throw new Error(resolved.error || 'Content Planner: record context request failed')
        }
        return resolved
      })
  }

  buildShell(activeTab) {
    const wrapper = document.createElement('div')
    wrapper.className = 'content-planner-record-modal'

    if (this.info) {
      wrapper.append(this.buildContextRow())
    }

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

    this.applyInitialCounts()

    return wrapper
  }

  /**
   * Counts known up front, so the tabs say what is in them before any pane has been opened.
   */
  applyInitialCounts() {
    const counts = this.info?.counts
    if (!counts) {
      return
    }

    this.updateTabCount('comments', counts.comments)
    this.updateTabCount('watch', counts.watchers)

    this.setAssigneeDetail(counts.assignee)
  }

  /** The loaded Assignee tab knows who is assigned now, which the tab label and the next request must follow. */
  syncAssigneeFromPane(pane) {
    // The same short name the tab carried before it was loaded, not the longer card heading.
    const person = pane.querySelector('.content-planner-assignee__person[data-assignee-short-name]')
    this.setAssigneeDetail(person?.dataset.assigneeShortName ?? '')
  }

  /** The name after the Assignee tab label, empty for a record nobody is assigned to. */
  setAssigneeDetail(name) {
    const button = this.tabButtons.assignee
    let suffix = button.querySelector('.content-planner-record-modal__tab-detail')
    if (!name) {
      suffix?.remove()
      return
    }
    if (!suffix) {
      suffix = document.createElement('span')
      suffix.className = 'content-planner-record-modal__tab-detail'
      button.append(suffix)
    }
    suffix.textContent = name
  }

  buildContextRow() {
    const row = document.createElement('div')
    row.className = 'content-planner-record-modal__context'

    const record = document.createElement('div')
    record.className = 'content-planner-record-modal__record'

    const icon = document.createElement('span')
    icon.className = 'content-planner-record-modal__record-icon'
    icon.innerHTML = this.info.recordIcon
    record.append(icon)

    const title = document.createElement('strong')
    title.className = 'content-planner-record-modal__record-title'
    title.textContent = this.info.title
    record.append(title)

    const reference = document.createElement('small')
    reference.className = 'content-planner-record-modal__record-reference'
    reference.textContent = `${this.info.typeLabel} · ${this.info.reference}`
    record.append(reference)
    row.append(record)

    const status = document.createElement('div')
    status.className = 'content-planner-record-modal__status'
    const label = document.createElement('span')
    label.textContent = TYPO3.lang?.['modal.context.status'] || 'Status'
    status.append(label)
    this.statusControl = document.createElement('div')
    this.statusControl.className = 'dropdown'
    status.append(this.statusControl)
    this.renderStatusControl()
    row.append(status)

    if (this.info.workspace) {
      const note = document.createElement('p')
      note.className = 'content-planner-record-modal__workspace-note'
      note.setAttribute('role', 'note')
      note.textContent = TYPO3.lang?.['modal.context.workspaceNote'] || 'Status changes apply live, not to the workspace version.'
      row.append(note)
    }

    return row
  }

  /**
   * The status menu lists only the statuses the user may set. Without that permission the
   * current status is shown as plain text, never as a dead button.
   */
  renderStatusControl() {
    const { status, statuses, canChangeStatus, canUnsetStatus } = this.info
    const noStatus = TYPO3.lang?.['modal.context.noStatus'] || 'No status'
    const control = this.statusControl
    control.replaceChildren()

    if (!canChangeStatus) {
      const text = document.createElement('span')
      text.className = 'content-planner-record-modal__status-text'
      text.textContent = status ? status.title : noStatus
      control.append(text)
      return
    }

    const toggle = document.createElement('button')
    toggle.type = 'button'
    toggle.className = 'btn btn-default dropdown-toggle'
    toggle.setAttribute('aria-haspopup', 'true')
    toggle.setAttribute('aria-expanded', 'false')
    if (status) {
      toggle.insertAdjacentHTML('beforeend', this.decorativeIcon(status.icon))
    }
    toggle.append(document.createTextNode(` ${status ? status.title : noStatus}`))
    control.append(toggle)

    const menu = document.createElement('ul')
    menu.className = 'dropdown-menu dropdown-menu-end'
    statuses.forEach(entry => menu.append(this.buildStatusItem(entry.uid, entry.icon, entry.title, entry.current)))
    if (canUnsetStatus && status) {
      const divider = document.createElement('li')
      divider.innerHTML = '<hr class="dropdown-divider">'
      menu.append(divider)
      menu.append(this.buildStatusItem(0, '', TYPO3.lang?.['modal.context.removeStatus'] || 'Remove status', false))
    }
    control.append(menu)

    this.bindStatusMenu(toggle, menu)
  }

  /**
   * Bootstrap's dropdown positions this menu far outside the modal (Popper measures against the
   * dialog, which is wider than its content), so the menu is opened and positioned by hand: it
   * hangs below the button, aligned to its right edge (RecordModal.css).
   */
  bindStatusMenu(toggle, menu) {
    const setOpen = open => {
      menu.classList.toggle('show', open)
      toggle.setAttribute('aria-expanded', String(open))
    }

    const items = () => Array.from(menu.querySelectorAll('.dropdown-item'))
    const focusItem = item => item?.focus()
    // On open, the keyboard lands on the current status, or the first one.
    const openAndFocus = () => {
      setOpen(true)
      focusItem(menu.querySelector('.dropdown-item[aria-current="true"]') || items()[0])
    }

    toggle.addEventListener('click', event => {
      if (menu.classList.contains('show')) {
        setOpen(false)
      } else if (0 === event.detail) {
        // A click from the keyboard (Enter, Space), not from a pointer.
        openAndFocus()
      } else {
        setOpen(true)
      }
    })
    toggle.addEventListener('keydown', event => {
      if ('ArrowDown' === event.key) {
        event.preventDefault()
        openAndFocus()
      }
    })
    menu.addEventListener('keydown', event => {
      const list = items()
      const index = list.indexOf(document.activeElement)
      const target = {
        ArrowDown: list[(index + 1) % list.length],
        ArrowUp: list[(index - 1 + list.length) % list.length],
        Home: list[0],
        End: list[list.length - 1],
      }[event.key]
      if (target) {
        event.preventDefault()
        focusItem(target)
      } else if ('Tab' === event.key) {
        setOpen(false)
      }
    })
    menu.addEventListener('click', () => setOpen(false))
    const closeOnOutsideClick = event => {
      if (!toggle.isConnected) {
        document.removeEventListener('click', closeOnOutsideClick)
        return
      }
      if (!event.target.closest('.content-planner-record-modal__status')) {
        setOpen(false)
      }
    }
    document.addEventListener('click', closeOnOutsideClick)
    toggle.closest('.content-planner-record-modal__status')?.addEventListener('keydown', event => {
      if ('Escape' === event.key && menu.classList.contains('show')) {
        // preventDefault as well: in TYPO3 v14 the modal is a <dialog>, which closes on Escape by default.
        event.preventDefault()
        event.stopPropagation()
        setOpen(false)
        toggle.focus()
      }
    })
  }

  /**
   * The status icon markup carries its own `title` and a visually hidden copy of the status
   * name, which would make the name of the control read twice ("Pending Pending"): the text
   * next to the icon is the name.
   */
  decorativeIcon(markup) {
    const template = document.createElement('template')
    template.innerHTML = markup
    template.content.querySelectorAll('[title]').forEach(element => element.removeAttribute('title'))
    template.content.querySelectorAll('title, .visually-hidden').forEach(element => element.remove())

    return template.innerHTML
  }

  buildStatusItem(uid, iconMarkup, title, current) {
    const item = document.createElement('li')
    const button = document.createElement('button')
    button.type = 'button'
    button.className = `dropdown-item${current ? ' active' : ''}`
    if (current) {
      button.setAttribute('aria-current', 'true')
    }
    button.insertAdjacentHTML('beforeend', this.decorativeIcon(iconMarkup))
    button.append(document.createTextNode(` ${title}`))
    button.addEventListener('click', () => this.changeStatus(uid))
    item.append(button)

    return item
  }

  changeStatus(statusUid) {
    const { table, uid } = this.context
    const recordKey = this.recordKey

    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_modalstatus)
      .post({ table, uid, status: statusUid })
      .then(async response => {
        const resolved = await response.resolve()
        if (!response.response.ok || resolved.error) {
          throw new Error(resolved.error || 'Content Planner: status change request failed')
        }
        if (this.recordKey === recordKey) {
          this.info = { ...this.info, status: resolved.status, statuses: resolved.statuses }
          this.renderStatusControl()
          // Refreshing the content frame now would replace the realm this modal's handlers run
          // in, and the open modal would stop responding. It is refreshed when the modal closes.
          this.pageStale = true
        } else {
          Viewport.ContentContainer.refresh()
        }
        Notification.message(0 === statusUid ? 'status.reset' : 'status.changed', 'success')
      })
      .catch(error => {
        console.error('Content Planner: failed to change the status:', error)
        Notification.message(0 === statusUid ? 'status.reset' : 'status.changed', 'failure')
      })
  }

  buildFooterButtons() {
    const buttons = []

    if (this.context.editUri) {
      buttons.push({
        text: TYPO3.lang?.['button.modal.footer.edit'] || 'Edit record',
        name: 'edit',
        icon: 'actions-flag-edit',
        btnClass: 'btn-default',
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
      btnClass: 'btn-default',
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
        return this.appendStyled(pane, payload.result).then(() => this.onPaneReady(tab, pane, payload))
      })
      .catch(error => {
        console.error(`Content Planner: failed to load the "${tab}" tab:`, error)
        delete pane.dataset.loaded
      })
  }

  /**
   * The pane markup ships its own stylesheet links. Showing it before they have loaded flashes
   * unstyled content, so the pane stays invisible until they settle (or a short timeout passes).
   */
  appendStyled(pane, markup) {
    const fragment = document.createRange().createContextualFragment(markup)
    const sheets = Array.from(fragment.querySelectorAll('link[rel="stylesheet"]'))
    const loaded = Promise.all(sheets.map(sheet => new Promise(resolve => {
      sheet.addEventListener('load', resolve, { once: true })
      sheet.addEventListener('error', resolve, { once: true })
    })))

    pane.style.visibility = 'hidden'
    pane.appendChild(fragment)

    let timer
    const timeout = new Promise(resolve => { timer = setTimeout(resolve, 3000) })

    return Promise.race([loaded, timeout]).finally(() => {
      clearTimeout(timer)
      pane.style.visibility = ''
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

    if ('watch' === tab) {
      return new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_watchers)
        .withQueryArguments({ table, uid })
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
      this.syncAssigneeFromPane(pane)
      return
    }

    if ('watch' === tab) {
      // The toggle button inside this pane is the same WatchToggle partial the header
      // renders, wired up by watch-toggle.js's document-level click delegation - nothing
      // extra to bind here.
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
      this.clickComposerTriggerWhenBound(composerTrigger)
    }
  }

  /**
   * `typo3:contentplanner:reinitializelistener` is dispatched on `this.modal`, which
   * `Modal.advanced()` always attaches to the TOP document. The listener that binds
   * `[data-comment-composer-trigger]` (comment-composer.js) only reaches that document once
   * its own module has loaded there - which happens lazily, via the AJAX response that
   * fetched this very pane, not eagerly like the page module's own copy of the same module.
   * On a session's first modal open that load is still in flight when this runs, so a plain
   * `composerTrigger.click()` right after the dispatch above silently does nothing: the
   * click fires before any listener is bound to catch it. Retrying against
   * `dataset.commentComposerBound` (set by that listener once it binds) rides out the race
   * instead of assuming the dispatch above was synchronous.
   */
  clickComposerTriggerWhenBound(trigger, attempt = 0) {
    if (!trigger) {
      return
    }
    if ('true' === trigger.dataset.commentComposerBound) {
      trigger.click()
      return
    }
    if (attempt >= 40) {
      return
    }
    setTimeout(() => this.clickComposerTriggerWhenBound(trigger, attempt + 1), 50)
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
