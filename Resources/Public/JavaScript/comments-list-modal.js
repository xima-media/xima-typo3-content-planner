/**
* Module: @xima/ximatypo3contentplanner/comments-list-modal
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Modal from "@typo3/backend/modal.js"
import CreateAndEditCommentModal from "@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js"

class CommentsListModal {

  constructor() {
    document.querySelectorAll('[data-content-planner-comments]').forEach(item => {
      item.addEventListener('click', e => {
        e.preventDefault()
        const url = item.getAttribute('href') && !item.hasAttribute('data-force-ajax-url')
          ? item.getAttribute('href')
          : TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
        this.fetchComments(
          url,
          item.getAttribute('data-table'),
          item.getAttribute('data-id'),
          item.getAttribute('data-new-comment-uri'),
          item.getAttribute('data-edit-uri')
        )
      })
    })
  }

  fetchComments(url, table, uid, newCommentUrl = false, editUrl = false) {
    const buttons = [
      ...(newCommentUrl ? [{
        text: TYPO3.lang?.['button.modal.footer.new'] || 'New',
        name: 'new',
        icon: 'actions-message-add',
        active: true,
        btnClass: 'btn-primary',
        trigger: (event, modal) => {
          CreateAndEditCommentModal.openModal(newCommentUrl, modal.querySelector('#content-planner-comment-list'), table, uid)
        }
      }] : []),
      ...(editUrl ? [{
        text: TYPO3.lang?.['button.modal.footer.edit'] || 'Edit',
        name: 'edit',
        icon: 'actions-flag-edit',
        active: true,
        btnClass: 'btn-secondary',
        trigger: (event, modal) => {
          modal.hideModal()
          setTimeout(() => window.location.href = editUrl, 100)
        }
      }] : []),
      {
        text: TYPO3.lang?.['button.modal.footer.close'] || 'Close',
        name: 'close',
        icon: 'actions-close',
        active: true,
        btnClass: 'btn-secondary',
        trigger: (event, modal) => modal.hideModal()
      }
    ]

    new AjaxRequest(url)
      .withQueryArguments({table, uid})
      .get()
      .then(async response => {
        const resolved = await response.resolve()
        Modal.advanced({
          title: TYPO3.lang?.['button.modal.header.comments'] || 'Comments',
          content: document.createRange().createContextualFragment(resolved.result),
          size: Modal.sizes.large,
          staticBackdrop: true,
          buttons,
          callback: (modal) => {
            modal.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {
              bubbles: true,
              composed: true
            }))
          }
        })
      })
  }
}

export default new CommentsListModal()
