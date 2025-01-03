/**
* Module: @xima/ximatypo3contentplanner/new-comment-modal
*/
import Modal from "@typo3/backend/modal.js";
import Viewport from "@typo3/backend/viewport.js";
import Notification from "@typo3/backend/notification.js";


class NewCommentModal {

  constructor() {
    const item = document.querySelector('#new-comment-modal');
    if (item) {
      item.addEventListener('click', e => {
        e.preventDefault()
        const url = e.currentTarget.getAttribute('href')
        this.openNewCommentModal(url)
      })
    }
  }

  openNewCommentModal(url) {
    Modal.advanced({
      type: Modal.types.iframe,
      title: 'New comment',
      content: url,
      size: Modal.sizes.large,
      staticBackdrop: true,
      buttons: [
        {
          text: TYPO3.lang !== undefined && TYPO3.lang['button.modal.footer.close'] ? TYPO3.lang['button.modal.footer.close'] : 'Close',
          name: 'close',
          icon: 'actions-close',
          active: true,
          btnClass: 'btn-secondary',
          trigger: function (event, modal) {
            // workaround: prevent "Do you want to close without saving?" confirmation
            const iframe = modal.querySelector('.modal-body iframe');
            const iframeDocument = iframe.contentWindow.document;
            iframeDocument.querySelectorAll('.has-error,.has-change,.is-new').forEach(element => {
              element.classList.remove('has-error');
              element.classList.remove('has-change');
              element.classList.remove('is-new');
            });

            Viewport.ContentContainer.refresh();
            modal.hideModal();
          }
        }
      ],
      callback: (modal) => {
        const iframe = modal.querySelector('.modal-body iframe');
        let loadCount = 0;

        function onIframeLoad(event) {
          if (loadCount > 0) {
            // workaround: it's the second time the iframe is loaded, so we can assume the comment was saved
            Viewport.ContentContainer.refresh();
            modal.hideModal();
            Notification.success('New comment', 'Successfully saved comment to record.');
          }
          loadCount++;
          iframe.removeEventListener('load', onIframeLoad);
          setTimeout(function () {
            iframe.addEventListener('load', onIframeLoad);
          }, 0);
        }

        iframe.addEventListener('load', onIframeLoad);
      },
    });
  }
}

export default new NewCommentModal();
