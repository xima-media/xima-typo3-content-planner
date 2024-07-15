/**
 * Module: @xima/ximatypo3contentplanner/comments-modal
 */
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Modal from "@typo3/backend/modal.js";

class CommentsModal {

  constructor() {
    document.querySelectorAll('.contentPlanner--comments').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault();
        const url = item.hasAttribute('href') ? item.getAttribute('href') : TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments;
        const table = item.getAttribute('data-table');
        const id = item.getAttribute('data-id');
        this.fetchComments(url, table, id);
      });
    });
  }

  fetchComments(href, table, uid) {
    new AjaxRequest(href)
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
          title: 'Comments',
          content: document.createRange()
            .createContextualFragment(resolved.result),
          size: Modal.sizes.large,
          staticBackdrop: true,
          buttons: [
            {
              text: TYPO3.lang['button.modal.footer.close'],
              name: 'close',
              icon: 'actions-close',
              active: true,
              btnClass: 'btn-secondary',
              trigger: function (event, modal) {
                modal.hideModal();
              }
            }
          ]
        });

      });
  }
}

export default new CommentsModal();
