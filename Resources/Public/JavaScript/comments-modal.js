/**
* Module: @xima/ximatypo3contentplanner/comments-modal
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Modal from "@typo3/backend/modal.js";
import NewCommentModal from "@xima/ximatypo3contentplanner/new-comment-modal.js";

class CommentsModal {

  constructor() {
    document.querySelectorAll('[data-content-planner-comments]').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        const url = item.hasAttribute('href') && !item.hasAttribute('data-force-ajax-url') ? item.getAttribute('href') : TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments;
        const table = item.getAttribute('data-table');
        const id = item.getAttribute('data-id');
        const newCommentsUrl = item.getAttribute('data-new-comment-uri');
        const editUrl = item.getAttribute('data-edit-uri');
        this.fetchComments(url, table, id, newCommentsUrl, editUrl);
      });
    });
  }

  fetchComments(url, table, uid, newCommentUrl = false, editUrl = false) {
    let buttons = [{
      text: TYPO3.lang !== undefined && TYPO3.lang['button.modal.footer.close'] ? TYPO3.lang['button.modal.footer.close'] : 'Close',
      name: 'close',
      icon: 'actions-close',
      active: true,
      btnClass: 'btn-secondary',
      trigger: function (event, modal) {
        modal.hideModal();
      }
    }];
    if (editUrl) {
      buttons.unshift({
        text: TYPO3.lang !== undefined && TYPO3.lang['button.modal.footer.edit'] ? TYPO3.lang['button.modal.footer.edit'] : 'Edit',
        name: 'edit',
        icon: 'actions-flag-edit',
        active: true,
        btnClass: 'btn-secondary',
        trigger: function(event, modal) {
          modal.hideModal();
          setTimeout(() => {
            window.location.href = editUrl;
          }, 100);
        }
      });
    }
    if (newCommentUrl) {
      buttons.unshift({
        text: TYPO3.lang !== undefined && TYPO3.lang['button.modal.footer.new'] ? TYPO3.lang['button.modal.footer.new'] : 'New',
        name: 'new',
        icon: 'actions-message-add',
        active: true,
        btnClass: 'btn-primary',
        trigger: function (event, modal) {
          modal.hideModal();
          NewCommentModal.openNewCommentModal(newCommentUrl);
        }
      });
    }

    new AjaxRequest(url)
      .withQueryArguments(
        {
          table: table,
          uid: uid
        }
      )
      .get()
      .then(async (response) => {
        const resolved = await response.resolve();
        Modal.advanced({
          title: TYPO3.lang !== undefined && TYPO3.lang['button.modal.header.comments'] ? TYPO3.lang['button.modal.header.comments'] : 'Comments',
          content: document.createRange()
            .createContextualFragment(resolved.result),
          size: Modal.sizes.large,
          staticBackdrop: true,
          buttons: buttons
        });

        console.log('lets add event listeners');
        document.querySelectorAll('.widget-contentPlanner--comment').forEach(item => {
          item.addEventListener('click', e => {
            console.log(e.currentTarget);
          });
        });
      });
    }
  }

  export default new CommentsModal();
