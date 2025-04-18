/**
* Module: @xima/ximatypo3contentplanner/comments-modal
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Modal from "@typo3/backend/modal.js";
import NewCommentModal from "@xima/ximatypo3contentplanner/new-comment-modal.js";

class CommentsEdit {

  constructor() {
    console.log('lets add even');
    document.querySelectorAll('[data-edit-comment-uri]').forEach(item => {
      item.addEventListener('click', e => {
        console.log(e.currentTarget);
        console.log(item.getAttribute('data-edit-comment-uri'));
        const editCommentUrl = item.getAttribute('data-edit-comment-uri');
        NewCommentModal.openNewCommentModal(editCommentUrl);
      });
    });
  }
}

export default new CommentsEdit();
