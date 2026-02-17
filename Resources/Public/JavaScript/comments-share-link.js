/**
* Module: @xima/ximatypo3contentplanner/comments-share-link
*/

class CommentsShareLink {

  constructor() {
    window.addEventListener('typo3:contentplanner:reinitializelistener', () => {
      this.initEventListeners()
    })
  }

  initEventListeners() {
    document.querySelectorAll('[data-share-comment-uri], [data-share-modal-uri]').forEach(item => {
      if (item.dataset.shareListenerBound) {
        return
      }
      item.dataset.shareListenerBound = 'true'
      item.addEventListener('click', (event) => {
        event.preventDefault()
        const shareUrl = item.getAttribute('data-share-comment-uri') || item.getAttribute('data-share-modal-uri')
        this.copyToClipboard(shareUrl)
      })
    })
  }

  copyToClipboard(url) {
    const absoluteUrl = new URL(url, window.location.origin).href
    navigator.clipboard.writeText(absoluteUrl)
      .then(() => this.showSuccess())
      .catch(() => this.showError())
  }

  showSuccess() {
    top.TYPO3.Notification.success(
      TYPO3.lang?.['comment.actions.share'] || 'Share link',
      TYPO3.lang?.['comment.share.copied'] || 'Link copied to clipboard'
    )
  }

  showError() {
    top.TYPO3.Notification.error(
      TYPO3.lang?.['comment.actions.share'] || 'Share link',
      TYPO3.lang?.['comment.share.failed'] || 'Failed to copy link'
    )
  }
}

export default new CommentsShareLink()
