import Modal from "@typo3/backend/modal.js"
import Viewport from "@typo3/backend/viewport.js"

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
        let loadCount = 0

        const onIframeLoad = () => {
          if (loadCount > 0) {
            Viewport.ContentContainer.refresh()
            modal.hideModal()
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
          loadCount++
          iframe.removeEventListener('load', onIframeLoad)
          setTimeout(() => iframe.addEventListener('load', onIframeLoad), 0)
        }
        iframe.addEventListener('load', onIframeLoad)
      },
    })
  }
}

export default new CreateAndEditCommentModal()
