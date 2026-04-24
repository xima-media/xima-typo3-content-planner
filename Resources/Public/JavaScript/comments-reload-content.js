/**
* Module: @xima/ximatypo3contentplanner/comments-reload-content
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import CommentsEditItem from "@xima/ximatypo3contentplanner/comments-edit-item.js"
import CommentsResolvedItem from "@xima/ximatypo3contentplanner/comments-resolved-item.js";
import CommentsDeleteItem from "@xima/ximatypo3contentplanner/comments-delete-item.js"
import CommentsShareLink from "@xima/ximatypo3contentplanner/comments-share-link.js"
import CreateAndEditCommentModal from "@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js"

class CommentsReloadContent {

  constructor() {
    document.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {bubbles: true, composed: true}))
    window.addEventListener('typo3:contentplanner:reloadcomments', ({detail: {url, table, id}}) => {
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

    document.querySelector('form#content-planner-comment-filter')?.addEventListener('change', (event) => {
      event.preventDefault()
      const url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
      const table = event.target.closest('form').getAttribute('data-table')
      const uid = event.target.closest('form').getAttribute('data-id')
      this.loadComments(url, table, uid)
    })

    document.querySelectorAll('[data-new-comment-uri]').forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault()
        const table = event.target.getAttribute('data-table')
        const id = event.target.getAttribute('data-id')
        const newCommentUrl = event.target.getAttribute('data-new-comment-uri')
        CreateAndEditCommentModal.openModal(newCommentUrl, document.querySelector('#content-planner-comment-list'), table, id)
      })
    })

    if (!this.replyDelegateInitialized) {
      document.addEventListener('click', event => {
        const target = event.target.closest('[data-reply-comment-uri]')
        if (!target) {
          return
        }
        event.preventDefault()
        const table = target.getAttribute('data-table')
        const id = target.getAttribute('data-id')
        const replyUrl = target.getAttribute('data-reply-comment-uri')
        const parentUid = new URL(replyUrl, window.location.origin).searchParams
          .get('defVals[tx_ximatypo3contentplanner_comment][parent_uid]')
        this.pendingHighlightParentUid = parentUid || null
        CreateAndEditCommentModal.openModal(replyUrl, document.querySelector('#content-planner-comment-list'), table, id)
      })
      this.replyDelegateInitialized = true
    }
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

  getFilterValues() {
    const filterForm = document.querySelector('form#content-planner-comment-filter')
    if (!filterForm) {
      console.warn('Filter form not found')
      return null
    }
    const formData = new FormData(filterForm)
    return Object.fromEntries(formData.entries())
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
        CommentsEditItem.initEventListeners()
        CommentsResolvedItem.initEventListeners()
        CommentsDeleteItem.initEventListeners()
        CommentsShareLink.initEventListeners()
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
