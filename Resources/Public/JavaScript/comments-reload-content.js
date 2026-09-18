/**
* Module: @content-planner/comments-reload-content
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import CommentsResolvedItem from "@content-planner/comments-resolved-item.js";
import CommentsDeleteItem from "@content-planner/comments-delete-item.js"
import CommentsShareLink from "@content-planner/comments-share-link.js"
import CommentComposer from "@content-planner/comment-composer.js"
import CommentTodoToggle from "@content-planner/comment-todo-toggle.js"
import CommentMentionCard from "@content-planner/comment-mention-card.js"

class CommentsReloadContent {

  constructor() {
    document.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {bubbles: true, composed: true}))
    window.addEventListener('typo3:contentplanner:reloadcomments', ({detail: {url, table, id, highlightParentUid}}) => {
      this.pendingHighlightParentUid = highlightParentUid || null
      this.loadComments(url, table, id)
    })
    window.addEventListener('typo3:contentplanner:reinitializelistener', () => {
      this.initEventListeners()
    })
    this.initEventListeners()
  }

  initEventListeners() {
    this.initCommentHover()
    this.initRepliesToggle()
    this.initIncludeChildCommentsToggle()
    this.initNewCommentTrigger()

    document.querySelector('form#content-planner-comment-filter')?.addEventListener('change', (event) => {
      event.preventDefault()
      const url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
      const table = event.target.closest('form').getAttribute('data-table')
      const uid = event.target.closest('form').getAttribute('data-id')

      this.loadComments(url, table, uid)
    })

    // The composer is always rendered inline at the bottom of the list (CP-28, #327) - a
    // "new comment" trigger elsewhere (header button, list/tree context menu) only needs to
    // bring it into view, not fetch or open anything.
    document.querySelectorAll('[data-new-comment-uri]').forEach(item => {
      if ('true' === item.dataset.commentComposerBound) {
        return
      }
      item.dataset.commentComposerBound = 'true'
      item.addEventListener('click', event => {
        event.preventDefault()
        this.focusNewCommentComposer()
      })
    })
  }

  // The record-modal.js tab bar replaced the modal footer's own "New" button (it applied to
  // both tabs, but the merged modal's footer is now static Edit/Close) with this row-level
  // equivalent: same behaviour, expand and scroll to the already-inline composer trigger.
  initNewCommentTrigger() {
    document.querySelectorAll('[data-comment-new-trigger]').forEach(item => {
      if ('true' === item.dataset.commentNewTriggerBound) {
        return
      }
      item.dataset.commentNewTriggerBound = 'true'
      item.addEventListener('click', event => {
        event.preventDefault()
        const composerTrigger = document.querySelector('#content-planner-comment-list [data-comment-composer-trigger]')
        composerTrigger?.scrollIntoView({behavior: 'smooth', block: 'center'})
        composerTrigger?.click()
      })
    })
  }

  focusNewCommentComposer() {
    const composer = document.querySelector('#content-planner-comment-list')
      ?.parentElement?.querySelector('[data-comment-composer][data-mode="new"]')
    if (!composer) {
      return
    }
    composer.scrollIntoView({behavior: 'smooth', block: 'center'})
    composer.querySelector('typo3-rte-ckeditor-ckeditor5 textarea')?.focus()
  }

  initRepliesToggle() {
    document.querySelectorAll('[data-toggle-replies-expanded]').forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault()
        const newValue = item.getAttribute('data-toggle-replies-expanded')

        new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_usersetting)
          .withQueryArguments({key: 'repliesExpanded', value: newValue})
          .get()
          .then(() => {
            const filterForm = document.querySelector('form#content-planner-comment-filter')
            if (filterForm) {
              const url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
              const table = filterForm.getAttribute('data-table')
              const uid = filterForm.getAttribute('data-id')
              this.loadComments(url, table, uid)
            }
          })
          .catch((error) => {
            console.error('Failed to save user setting:', error)
            top.TYPO3.Notification.error('Error', 'Failed to save setting.')
          })
      })
    })
  }

  // includeChildComments (CP-29, #328) is a persisted user setting, not just a per-request
  // filter, and now lives as a checkbox in the "..." dropdown (moved out of the filter form) -
  // save it before reloading so the new state survives the next time the panel opens. Its
  // checkbox still lives inside #content-planner-comment-filter, so its native `change` event
  // would otherwise also reach the form-level listener below and trigger a second, premature
  // reload (before the setting has actually been persisted) - stopPropagation() prevents that.
  initIncludeChildCommentsToggle() {
    document.querySelectorAll('[data-toggle-include-child-comments]').forEach(item => {
      item.addEventListener('change', event => {
        event.stopPropagation()
        const newValue = event.target.checked ? '1' : '0'

        new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_usersetting)
          .withQueryArguments({key: 'includeChildComments', value: newValue})
          .get()
          .then(() => {
            const filterForm = document.querySelector('form#content-planner-comment-filter')
            if (filterForm) {
              const url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
              const table = filterForm.getAttribute('data-table')
              const uid = filterForm.getAttribute('data-id')
              this.loadComments(url, table, uid)
            }
          })
          .catch((error) => {
            console.error('Failed to save user setting:', error)
            top.TYPO3.Notification.error('Error', 'Failed to save setting.')
          })
      })
    })
  }

  initCommentHover() {
    const container = document.querySelector('#content-planner-comment-list')
    if (!container || container.dataset.hoverInitialized) {
      return
    }
    container.dataset.hoverInitialized = 'true'
    container.addEventListener('mouseover', (event) => {
      const comment = event.target.closest('[data-comment-uid]')
      if (comment && !comment.classList.contains('content-planner-comment--hover')) {
        container.querySelectorAll('.content-planner-comment--hover')
          .forEach(el => el.classList.remove('content-planner-comment--hover'))
        comment.classList.add('content-planner-comment--hover')
      }
    })
    container.addEventListener('mouseleave', () => {
      container.querySelectorAll('.content-planner-comment--hover')
        .forEach(el => el.classList.remove('content-planner-comment--hover'))
    })
  }

  // showResolvedComments/showTodoComments are now real `name`d checkboxes inside the form
  // (not persisted settings, just transient view filters), so FormData already carries their
  // state on its own: present with value "1" when checked, absent entirely when not - which
  // the controller already treats as falsy via `?? false`.
  getFilterValues() {
    const filterForm = document.querySelector('form#content-planner-comment-filter')
    if (!filterForm) {
      console.warn('Filter form not found')
      return null
    }
    return Object.fromEntries(new FormData(filterForm).entries())
  }

  loadComments(url, table, uid) {
    if (!url || !table || !uid) {
      console.warn('Missing parameters for loading comments:', {url, table, uid})
      return
    }

    const filterValues = this.getFilterValues()
    let queryArguments = {table, uid, ...(filterValues || {})}
    new AjaxRequest(url)
      .withQueryArguments(queryArguments)
      .get()
      .then(async (response) => {
        const resolved = await response.resolve()
        const commentList = document.querySelector('#content-planner-comment-list')
        if (!commentList) {
          console.warn('Comment list container not found')
          return
        }
        const parent = commentList.parentElement
        parent.innerHTML = resolved.result
        // After the replacement, so the orphaned triggers are the ones already detached.
        CommentMentionCard.disposeOrphaned()
        CommentsResolvedItem.initEventListeners()
        CommentsDeleteItem.initEventListeners()
        CommentsShareLink.initEventListeners()
        CommentComposer.initEventListeners()
        CommentTodoToggle.initEventListeners()
        this.initEventListeners()
        this.highlightNewReply(parent)
      })
      .catch((error) => {
        console.error('Failed to load comments:', error)
        top.TYPO3.Notification.error('Error', 'Failed to load comments.')
      })
  }

  highlightNewReply(container) {
    const parentUid = this.pendingHighlightParentUid
    this.pendingHighlightParentUid = null
    if (!parentUid) {
      return
    }

    const collapseEl = container.querySelector(`#replies-${CSS.escape(parentUid)}`)
    if (!collapseEl) {
      return
    }

    // Expand the collapse
    collapseEl.classList.add('show')
    const toggle = container.querySelector(`[aria-controls="replies-${CSS.escape(parentUid)}"]`)
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true')
    }

    // Highlight the newest reply (highest UID = most recently created)
    const replies = [...collapseEl.querySelectorAll('[data-comment-uid]')]
    const newestReply = replies.reduce((a, b) =>
      parseInt(a.dataset.commentUid) > parseInt(b.dataset.commentUid) ? a : b
    , replies[0])
    if (newestReply) {
      newestReply.scrollIntoView({behavior: 'smooth', block: 'center'})
      newestReply.classList.add('content-planner-comment--highlight')
      setTimeout(() => newestReply.classList.remove('content-planner-comment--highlight'), 2500)
    }
  }
}

export default new CommentsReloadContent()
