/**
* Module: @xima/ximatypo3contentplanner/comments-resolved-item
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Modal from "@typo3/backend/modal.js"
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class CommentsResolvedItem {

  constructor() {
    window.addEventListener('typo3:contentplanner:reinitializelistener', () => {
      this.initEventListeners()
    })
  }

  initEventListeners() {
    document.querySelectorAll('[data-resolved-comment-uri]').forEach(item => {
      item.addEventListener('click', ({currentTarget}) => {
        const resolvedCommentUrl = currentTarget.getAttribute('data-resolved-comment-uri')
        const resolvedCommentTitle = currentTarget.getAttribute('data-resolved-comment-title')
        const resolvedCommentDescription = currentTarget.getAttribute('data-resolved-comment-description')
        const resolvedCommentButton = currentTarget.getAttribute('data-resolved-comment-button')

        Modal.confirm(resolvedCommentTitle, resolvedCommentDescription, TYPO3.Severity.warning, [
          {
            text: resolvedCommentButton,
            active: true,
            trigger: () => {
              new AjaxRequest(resolvedCommentUrl)
                .get()
                .then(async result => {
                  this.reloadComments(currentTarget)
                  Modal.dismiss()
                  Notification.message(
                    'comment.resolve',
                    result.response.ok ? 'success' : 'failure'
                  )
                })
                .catch((error) => {
                  console.error('Comment resolve failed:', error)
                  Modal.dismiss()
                })
            }
          },
          {
            text: 'Cancel',
            trigger: () => {
              Modal.dismiss()
            }
          }
        ])
      })
    })
  }

  reloadComments(target) {
    const eventDetail = {
      url: TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments,
      table: target.getAttribute('data-table'),
      id: target.getAttribute('data-id')
    }

    const commentList = document.querySelector('#content-planner-comment-list')
    if (!commentList) {
      console.warn('Comment list container not found')
      return
    }
    commentList
      .dispatchEvent(new CustomEvent('typo3:contentplanner:reloadcomments', {
        detail: eventDetail,
        bubbles: true,
        composed: true
      }))
  }
}

export default new CommentsResolvedItem()
