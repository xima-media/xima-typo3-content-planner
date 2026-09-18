/**
* Module: @content-planner/comments-list-modal
*/
import RecordModal from "@content-planner/record-modal.js"

class CommentsListModal {

  constructor() {
    document.querySelectorAll('[data-content-planner-comments]').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault()
        const url = item.getAttribute('href') && !item.hasAttribute('data-force-ajax-url')
          ? item.getAttribute('href')
          : TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
        this.fetchComments(
          url,
          item.getAttribute('data-table'),
          item.getAttribute('data-id'),
          item.getAttribute('data-new-comment-uri'),
          item.getAttribute('data-edit-uri'),
          null,
          false,
          item.hasAttribute('data-focus-composer'),
          item.hasAttribute('data-show-todo-comments')
        )
      })
    })

    this.handleDeepLink()
  }

  handleDeepLink() {
    const params = new URLSearchParams(window.location.search)
    const shouldOpenComments = params.get('tx_contentplanner_comments')
    const targetCommentUid = params.get('tx_contentplanner_comment')
    const commentResolved = params.get('tx_contentplanner_comment_resolved')

    if (!shouldOpenComments) {
      return
    }

    const trigger = document.querySelector('[data-content-planner-comments]')
    if (!trigger) {
      return
    }

    // Clean URL params immediately
    const cleanUrl = new URL(window.location.href)
    cleanUrl.searchParams.delete('tx_contentplanner_comments')
    cleanUrl.searchParams.delete('tx_contentplanner_comment')
    cleanUrl.searchParams.delete('tx_contentplanner_comment_resolved')
    history.replaceState(null, '', cleanUrl.toString())

    setTimeout(() => {
      const url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
      this.fetchComments(
        url,
        trigger.getAttribute('data-table'),
        trigger.getAttribute('data-id'),
        trigger.getAttribute('data-new-comment-uri'),
        trigger.getAttribute('data-edit-uri'),
        targetCommentUid || null,
        !!commentResolved
      )
    }, 300)
  }

  /**
   * `newCommentUrl` is kept for call-site compatibility (every existing trigger still passes
   * it positionally) but is no longer read here: whether the composer is offered at all is
   * already decided server-side (Default/Comments.html renders it only when the record's
   * `commentComposerHtml` is non-empty, the same permission check that used to gate this
   * parameter), and the "New" trigger itself moved into the comments pane's own toolbar.
   */
  fetchComments(url, table, uid, newCommentUrl = false, editUrl = false, scrollToCommentUid = null, showResolved = false, focusComposer = false, showTodo = false) {
    RecordModal.open('comments', {
      table,
      uid,
      commentsUrl: url,
      editUri: editUrl || false,
      scrollToCommentUid,
      showResolvedComments: showResolved,
      showTodoComments: showTodo,
      focusComposer,
    })
  }
}

export default new CommentsListModal()
