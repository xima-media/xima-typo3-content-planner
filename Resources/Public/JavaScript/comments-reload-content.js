/**
* Module: @xima/ximatypo3contentplanner/comments-reload-content
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import CommentsEditItem from "@xima/ximatypo3contentplanner/comments-edit-item.js"
import CommentsDeleteItem from "@xima/ximatypo3contentplanner/comments-delete-item.js"

class CommentsReloadContent {

  constructor() {
    document.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {bubbles: true, composed: true}))
    window.addEventListener('typo3:contentplanner:reloadcomments', ({detail: {url, table, id}}) => {
      this.loadComments(url, table, id)
    })
  }

  loadComments(url, table, uid) {
    new AjaxRequest(url)
      .withQueryArguments({table, uid})
      .get()
      .then(async (response) => {
        const resolved = await response.resolve()
        const commentList = document.querySelector('#widget-contentPlanner--comment-list')
        if (!commentList) {
          console.warn('Comment list container not found')
          return
        }
        const parent = commentList.parentElement
        parent.innerHTML = resolved.result
        CommentsEditItem.initEventListeners()
        CommentsDeleteItem.initEventListeners()
      })
      .catch((error) => {
        console.error('Failed to load comments:', error)
        top.TYPO3.Notification.error('Error', 'Failed to load comments.')
      })
  }
}

export default new CommentsReloadContent()
