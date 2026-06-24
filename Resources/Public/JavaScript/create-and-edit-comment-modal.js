/**
* Module: @xima/ximatypo3contentplanner/create-and-edit-comment-modal
*/
import Modal from "@typo3/backend/modal.js"
import Viewport from "@typo3/backend/viewport.js"
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Notification from "@xima/ximatypo3contentplanner/notification.js";

class CreateAndEditCommentModal {

  constructor() {
    const item = document.querySelector('#create-and-edit-comment-modal')
    if (item) {
      item.addEventListener('click', e => {
        e.preventDefault()
        this.openModal(e.currentTarget.getAttribute('href'))
      })
    }
  }

  openModal(url, element = null, table = null, uid = null) {
    Modal.advanced({
      type: Modal.types.iframe,
      title: 'Create/Edit comment',
      content: url,
      size: Modal.sizes.large,
      staticBackdrop: true,
      buttons: [
        {
          text: TYPO3.lang?.['button.modal.footer.close'] || 'Close',
          name: 'close',
          icon: 'actions-close',
          active: true,
          btnClass: 'btn-secondary',
          trigger: (event, modal) => {
            const iframe = modal.querySelector('.modal-body iframe')
            iframe.contentWindow.document.querySelectorAll('.has-error,.has-change,.is-new')
              .forEach(el => el.classList.remove('has-error', 'has-change', 'is-new'))
            Viewport.ContentContainer.refresh()
            modal.hideModal()
          }
        }
      ],
      callback: (modal) => {
        const iframe = modal.querySelector('.modal-body iframe')

        iframe.addEventListener('load', function initialLoad() {
          const isNew = iframe.contentWindow.document.querySelector('.is-new') !== null
          iframe.removeEventListener('load', initialLoad)
          iframe.addEventListener('load', function afterSubmit() {
            const finish = () => {
              Viewport.ContentContainer.refresh()
              modal.hideModal()

              Notification.message(isNew ? 'comment.create' : 'comment.edit', 'success')
              if (element) {
                element.dispatchEvent(new CustomEvent('typo3:contentplanner:reloadcomments', {
                  detail: {
                    url: TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments,
                    table,
                    id: uid
                  },
                  bubbles: true,
                  composed: true
                }))
              }
            }

            // Saving re-renders the comment in edit mode, registering it as an "open document".
            // Clean up that orphaned reference before tearing down the modal (see issue #238).
            new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_closedocument)
              .get()
              .then(() => {
                // Official opendocs refresh signal — the toolbar (top frame) re-renders on this event.
                // No-op when opendocs is not installed (no listener registered).
                try {
                  top.document.dispatchEvent(new CustomEvent('typo3:opendocs:updateRequested'))
                } catch (e) {
                  // top frame not reachable — server-side state is already clean
                }
              })
              .catch(error => console.debug('Content Planner: close-document request did not complete:', error))
              .finally(finish)
          })
        })
      },
    })
  }
}

export default new CreateAndEditCommentModal()
