/**
* Module: @xima/ximatypo3contentplanner/comments-delete-item
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Modal from "@typo3/backend/modal.js"
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class CommentsDeleteItem {

  constructor() {
    window.addEventListener('typo3:contentplanner:reinitializelistener', () => {
      this.initEventListeners()
    })
  }

  initEventListeners() {
    document.querySelectorAll('[data-delete-comment-uri]').forEach(item => {
      item.addEventListener('click', ({currentTarget}) => {
        const deleteCommentUrl = currentTarget.getAttribute('data-delete-comment-uri')
        const deleteCommentTitle = currentTarget.getAttribute('data-delete-comment-title')
        const deleteCommentDescription = currentTarget.getAttribute('data-delete-comment-description')
        const deleteCommentButton = currentTarget.getAttribute('data-delete-comment-button')

        Modal.confirm(deleteCommentTitle, deleteCommentDescription, TYPO3.Severity.error, [
          {
            text: deleteCommentButton,
            active: true,
            trigger: () => {
              new AjaxRequest(deleteCommentUrl)
                .get()
                .then(async result => {
                  this.reloadComments(currentTarget)
                  Modal.dismiss()
                  Notification.message(
                    'comment.delete',
                    result.response.ok ? 'success' : 'failure'
                  )
                })
                .catch((error) => {
                  console.error('Comment deletion failed:', error)
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

    document.querySelector('#widget-contentPlanner--comment-list')
      .dispatchEvent(new CustomEvent('typo3:contentplanner:reloadcomments', {
        detail: eventDetail,
        bubbles: true,
        composed: true
      }))
  }
}

export default new CommentsDeleteItem()
