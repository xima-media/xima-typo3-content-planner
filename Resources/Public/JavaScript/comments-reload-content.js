/**
* Module: @xima/ximatypo3contentplanner/comments-reload-content
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import CommentsEditItem from "@xima/ximatypo3contentplanner/comments-edit-item.js"
import CommentsDeleteItem from "@xima/ximatypo3contentplanner/comments-delete-item.js"

class CommentsReloadContent {

  constructor() {
    CommentsEditItem.initEventListeners()
    CommentsDeleteItem.initEventListeners()
    window.addEventListener('typo3:contentplanner:reloadcomments', ({ detail: { url, table, id } }) => {
      this.loadComments(url, table, id)
    })
  }

  loadComments(url, table, uid) {
    new AjaxRequest(url)
      .withQueryArguments({ table, uid })
      .get()
      .then(async (response) => {
        const resolved = await response.resolve()
        const parent = document.querySelector('#widget-contentPlanner--comment-list').parentElement
        parent.innerHTML = resolved.result
        CommentsEditItem.initEventListeners()
        CommentsDeleteItem.initEventListeners()
      })
  }
}

export default new CommentsReloadContent()
