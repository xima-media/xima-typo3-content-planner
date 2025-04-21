/**
* Module: @xima/ximatypo3contentplanner/comments-edit-item
*/
import CreateAndEditCommentModal from "@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js"

class CommentsEditItem {

  initEventListeners() {
    document.querySelectorAll('[data-edit-comment-uri]').forEach(item => {
      item.addEventListener('click', ({currentTarget}) => {
        const table = currentTarget.getAttribute('data-table')
        const id = currentTarget.getAttribute('data-id')
        const editCommentUrl = currentTarget.getAttribute('data-edit-comment-uri')
        CreateAndEditCommentModal.openModal(editCommentUrl, document.querySelector('#widget-contentPlanner--comment-list'), table, id)
      })
    })
  }
}

export default new CommentsEditItem()
