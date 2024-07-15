/**
 * Module: @xima/ximatypo3contentplanner/new-comment-modal
 */
import Modal from "@typo3/backend/modal.js";
import Viewport from "@typo3/backend/viewport.js";


class NewCommentModal {

  constructor() {
    const item = document.querySelector('#new-comment-modal');
    if (item) {
      const pid = item.getAttribute('data-pid');
      const userid = item.getAttribute('data-userid');
      item.addEventListener('click', e => {
        e.preventDefault()
        const url = e.currentTarget.getAttribute('href')
        Modal.advanced({
          type: Modal.types.iframe,
          title: 'New comment',
          content: url,
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
                Viewport.ContentContainer.refresh();
                modal.hideModal();
              }
            }
          ]
        });
      })
    }
  }
}

export default new NewCommentModal();
