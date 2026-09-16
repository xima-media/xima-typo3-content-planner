/**
* Module: @content-planner/comment-composer
*
* Native comment composer (CP-28, #327): create/edit/reply happen inline in the comments view
* through the `<typo3-rte-ckeditor-ckeditor5>` web component, submitted via AJAX through the
* DataHandler. Replaces the former record_edit iframe modal (create-and-edit-comment-modal.js)
* and its #238 open-documents cleanup - there is no iframe and nothing gets registered as an
* open document anymore, so there is nothing left to clean up.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Notification from "@content-planner/notification.js"
import OptimisticUpdate from "@content-planner/optimistic-update.js"

let richTextEditorModule = null

/**
 * The CKEditor5 bundle is large and only needed once a composer is actually on screen. This
 * module is pulled in by the content planner header assets, which load on every backend page
 * showing a status header, so importing the bundle statically would make all of those pages
 * pay for it even when nobody opens a composer.
 *
 * Fire-and-forget is enough: `<typo3-rte-ckeditor-ckeditor5>` is a custom element, so an
 * instance already sitting in the DOM upgrades itself as soon as the module defines it.
 *
 * @returns {Promise<*>}
 */
function loadRichTextEditor() {
  if (!richTextEditorModule) {
    richTextEditorModule = import("@typo3/rte-ckeditor/ckeditor5.js")
      .catch(error => {
        richTextEditorModule = null
        throw error
      })
  }

  return richTextEditorModule
}

class CommentComposer {
  constructor() {
    window.addEventListener('typo3:contentplanner:reinitializelistener', (event) => {
      this.initEventListeners(event.detail?.modal || null)
    })
  }

  initEventListeners(modal = null) {
    const root = modal || document

    const forms = root.querySelectorAll('[data-comment-composer]')
    if (forms.length) {
      loadRichTextEditor()
    }
    forms.forEach(form => this.bindForm(form))

    root.querySelectorAll('[data-edit-comment-uri]').forEach(item => {
      if ('true' === item.dataset.commentComposerBound) {
        return
      }
      item.dataset.commentComposerBound = 'true'
      item.addEventListener('click', event => {
        event.preventDefault()
        this.openEditEditor(item)
      })
    })

    if (!this.replyDelegateInitialized) {
      document.addEventListener('click', event => {
        const target = event.target.closest('[data-reply-comment-uri]')
        if (!target) {
          return
        }
        event.preventDefault()
        this.openReplyEditor(target)
      })
      this.replyDelegateInitialized = true
    }
  }

  // --- opening the on-demand (edit/reply) composer ---------------------------------------

  openEditEditor(trigger) {
    const commentEl = trigger.closest('[data-comment-uid]')
    const textEl = commentEl?.querySelector('[data-comment-text]')
    if (!commentEl || !textEl) {
      return
    }

    if (commentEl.querySelector(':scope > .d-flex > [data-comment-composer]')) {
      return // already open
    }

    loadRichTextEditor()

    new AjaxRequest(trigger.getAttribute('data-edit-comment-uri'))
      .get()
      .then(async response => {
        const resolved = await response.resolve()
        const fragment = document.createRange().createContextualFragment(resolved.result)
        const form = fragment.querySelector('[data-comment-composer]')
        textEl.hidden = true
        textEl.insertAdjacentElement('afterend', form)
        this.bindForm(form, {
          onCancel: () => {
            form.remove()
            textEl.hidden = false
          },
        })
        // Keyboard users activated this deliberately; without moving focus they would have
        // to tab back to the editor that just appeared.
        form.querySelector('textarea')?.focus()
      })
      .catch(error => {
        console.error('Content Planner: failed to load the comment editor:', error)
        Notification.message('comment.edit', 'failure')
      })
  }

  openReplyEditor(trigger) {
    const commentEl = trigger.closest('[data-comment-uid]')
    const slot = commentEl?.querySelector(':scope > .d-flex > [data-reply-slot]')
    if (!commentEl || !slot) {
      return
    }

    if (slot.querySelector('[data-comment-composer]')) {
      slot.querySelector('[data-comment-composer] textarea')?.focus()
      return // already open
    }

    loadRichTextEditor()

    new AjaxRequest(trigger.getAttribute('data-reply-comment-uri'))
      .get()
      .then(async response => {
        const resolved = await response.resolve()
        const fragment = document.createRange().createContextualFragment(resolved.result)
        const form = fragment.querySelector('[data-comment-composer]')
        slot.appendChild(form)
        this.bindForm(form, {
          onCancel: () => form.remove(),
        })
        form.querySelector('textarea')?.focus()
      })
      .catch(error => {
        console.error('Content Planner: failed to load the reply editor:', error)
        Notification.message('comment.create', 'failure')
      })
  }

  // --- submission --------------------------------------------------------------------------

  bindForm(form, {onCancel} = {}) {
    if ('true' === form.dataset.commentComposerBound) {
      return
    }
    form.dataset.commentComposerBound = 'true'

    form.querySelector('[data-comment-composer-cancel]')?.addEventListener('click', () => onCancel?.())

    form.addEventListener('submit', event => {
      event.preventDefault()
      this.submit(form)
    })
  }

  submit(form) {
    const textarea = form.querySelector('textarea[slot="textarea"]')
    const submitButton = form.querySelector('[data-comment-composer-submit]')
    const content = textarea?.value?.trim() ?? ''
    if ('' === content) {
      return
    }

    // CP-27 (#326): comment-first flow. The picker fallback is a native <select required>,
    // so its own validity check is the "you must choose a status" enforcement; the one-click
    // flow instead carries its statusUid on the form itself (see Comments.html).
    const statusPicker = form.querySelector('[data-comment-status-picker]')
    if (statusPicker && !statusPicker.reportValidity()) {
      return
    }
    const statusUid = statusPicker?.value || form.dataset.statusUid || ''

    const mode = form.dataset.mode
    const table = form.dataset.table
    const id = form.dataset.id
    const parentUid = form.dataset.parentUid
    const commentUid = form.dataset.commentUid

    OptimisticUpdate.run({
      apply: () => this.applyPending(form, submitButton),
      request: () => new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_commentsave)
        .post({table, uid: id, content, commentUid, parentUid, statusUid})
        .then(async result => {
          const resolved = await result.resolve()
          if (!result.response.ok || resolved.error) {
            throw new Error(resolved.error || 'Content Planner: comment save request failed')
          }
          return resolved
        }),
      reconcile: resolved => this.reconcile(form, mode, resolved),
      rollback: snapshot => this.rollback(snapshot),
    }).then(() => {
      Notification.message('edit' === mode ? 'comment.edit' : 'comment.create', 'success')
    }).catch(() => {
      Notification.message('edit' === mode ? 'comment.edit' : 'comment.create', 'failure')
    })
  }

  applyPending(form, submitButton) {
    form.classList.add('content-planner-comment-composer--pending')
    if (submitButton) {
      submitButton.disabled = true
    }

    return {form, submitButton}
  }

  /**
   * Editing a comment in place is a simple, self-contained DOM swap. Creating a new comment or
   * reply, however, changes reply counts, the "N replies" toggle summary and the resolved-count
   * badge - all of it already computed correctly by the existing lightweight comments-fragment
   * reload (see comments-reload-content.js), so reconciling those two modes means triggering
   * that reload rather than hand-rebuilding the same server logic on the client. This is not
   * the "full content refresh" the composer replaces: no Viewport.ContentContainer.refresh(),
   * only the comment list fragment itself.
   */
  reconcile(form, mode, resolved) {
    if ('edit' === mode) {
      const fragment = document.createRange().createContextualFragment(resolved.result)
      form.closest('[data-comment-uid]')?.replaceWith(fragment.querySelector('[data-comment-uid]'))
      document.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {bubbles: true, composed: true}))

      return
    }

    const filterForm = document.querySelector('form#content-planner-comment-filter')
    const table = filterForm?.getAttribute('data-table') || form.dataset.table
    const id = filterForm?.getAttribute('data-id') || form.dataset.id
    document.querySelector('#content-planner-comment-list')?.dispatchEvent(new CustomEvent('typo3:contentplanner:reloadcomments', {
      detail: {
        url: TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments,
        table,
        id,
        highlightParentUid: 'reply' === mode ? form.dataset.parentUid : null,
      },
      bubbles: true,
      composed: true,
    }))
  }

  rollback(snapshot) {
    if (!snapshot) {
      return
    }
    snapshot.form.classList.remove('content-planner-comment-composer--pending')
    if (snapshot.submitButton) {
      snapshot.submitButton.disabled = false
    }
  }
}

export default new CommentComposer()
