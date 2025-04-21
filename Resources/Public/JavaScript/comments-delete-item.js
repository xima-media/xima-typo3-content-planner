/**
* Module: @xima/ximatypo3contentplanner/comments-delete-item
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Modal from "@typo3/backend/modal.js"

class CommentsDeleteItem {

  initEventListeners() {
    document.querySelectorAll('[data-delete-comment-uri]').forEach(item => {
      item.addEventListener('click', ({currentTarget}) => {
        const deleteCommentUrl = currentTarget.getAttribute('data-delete-comment-uri')

        Modal.confirm('Delete comment', 'Are you sure you want to delete this comment?', TYPO3.Severity.error, [
          {
            text: TYPO3.lang?.['delete.confirm'] || 'Delete',
            active: true,
            trigger: () => {
              new AjaxRequest(deleteCommentUrl).get().then(() => {
                top.TYPO3.Notification.warning('Delete', 'Comment entry successfully deleted.')
                this.reloadComments(currentTarget)
                Modal.dismiss()
              })
            }
          },
          {
            text: 'Cancel',
            trigger: () => { Modal.dismiss() }
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
