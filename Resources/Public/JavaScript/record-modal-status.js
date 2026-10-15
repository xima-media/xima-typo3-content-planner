/**
* Module: @content-planner/record-modal-status
*
* The status control in the context row of the record modal: the menu of the statuses the user
* may set, its keyboard operation, the request that changes the status and the refresh after a
* comment changed it. It works on the RecordModal instance it is created for.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Notification from "@content-planner/notification.js"
import Viewport from "@typo3/backend/viewport.js"

export default class RecordModalStatus {
  constructor(modal) {
    this.modal = modal
  }

  /**
   * The status menu lists only the statuses the user may set. Without that permission the
   * current status is shown as plain text, never as a dead button.
   */
  render() {
    const { status, statuses, canChangeStatus, canUnsetStatus } = this.modal.info
    const noStatus = TYPO3.lang?.['modal.context.noStatus'] || 'No status'
    const control = this.modal.statusControl
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
    statuses.forEach(entry => menu.append(this.buildItem(entry.uid, entry.icon, entry.title, entry.current)))
    if (canUnsetStatus && status) {
      const divider = document.createElement('li')
      divider.innerHTML = '<hr class="dropdown-divider">'
      menu.append(divider)
      menu.append(this.buildItem(0, '', TYPO3.lang?.['modal.context.removeStatus'] || 'Remove status', false))
    }
    control.append(menu)

    this.bindMenu(toggle, menu)
  }

  /**
   * Bootstrap's dropdown positions this menu far outside the modal (Popper measures against the
   * dialog, which is wider than its content), so the menu is opened and positioned by hand: it
   * hangs below the button, aligned to its right edge (RecordModal.css).
   */
  bindMenu(toggle, menu) {
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

  buildItem(uid, iconMarkup, title, current) {
    const item = document.createElement('li')
    const button = document.createElement('button')
    button.type = 'button'
    button.className = `dropdown-item${current ? ' active' : ''}`
    if (current) {
      button.setAttribute('aria-current', 'true')
    }
    button.insertAdjacentHTML('beforeend', this.decorativeIcon(iconMarkup))
    button.append(document.createTextNode(` ${title}`))
    button.addEventListener('click', () => this.change(uid))
    item.append(button)

    return item
  }

  change(statusUid) {
    const { table, uid } = this.modal.context
    const recordKey = this.modal.recordKey

    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_modalstatus)
      .post({ table, uid, status: statusUid })
      .then(async response => {
        const resolved = await response.resolve()
        if (!response.response.ok || resolved.error) {
          throw new Error(resolved.error || 'Content Planner: status change request failed')
        }
        if (this.modal.recordKey === recordKey) {
          this.modal.info = { ...this.modal.info, status: resolved.status, statuses: resolved.statuses }
          this.render()
          // Refreshing the content frame now would replace the realm this modal's handlers run
          // in, and the open modal would stop responding. It is refreshed when the modal closes.
          this.modal.pageStale = true
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

  /** A comment sent together with a new status changed it: the status control follows, the page when the modal closes. */
  refresh(table, uid) {
    if (!this.modal.modal || this.modal.recordKey !== `${table}:${uid}`) {
      return
    }
    this.modal.fetchRecordInfo().then(info => {
      if (this.modal.recordKey !== `${table}:${uid}`) {
        return
      }
      this.modal.info = { ...this.modal.info, status: info.status, statuses: info.statuses }
      this.render()
      this.modal.pageStale = true
    }).catch(error => console.error('Content Planner: failed to reload the status:', error))
  }
}
