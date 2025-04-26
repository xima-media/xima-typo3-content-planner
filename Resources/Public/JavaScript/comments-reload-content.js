/**
* Module: @xima/ximatypo3contentplanner/comments-reload-content
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import CommentsEditItem from "@xima/ximatypo3contentplanner/comments-edit-item.js"
import CommentsResolvedItem from "@xima/ximatypo3contentplanner/comments-resolved-item.js";
import CommentsDeleteItem from "@xima/ximatypo3contentplanner/comments-delete-item.js"
import CreateAndEditCommentModal from "@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js"

class CommentsReloadContent {

  constructor() {
    document.dispatchEvent(new CustomEvent('typo3:contentplanner:reinitializelistener', {bubbles: true, composed: true}))
    window.addEventListener('typo3:contentplanner:reloadcomments', ({detail: {url, table, id}}) => {
      this.loadComments(url, table, id)
    })
    this.initEventListeners()
  }

  initEventListeners() {
    document.querySelector('form#widget-contentPlanner--comment-filter')?.addEventListener('change', (event) => {
      event.preventDefault()
      const url = TYPO3.settings.ajaxUrls.ximatypo3contentplanner_comments
      const table = event.target.closest('form').getAttribute('data-table')
      const uid = event.target.closest('form').getAttribute('data-id')
      this.loadComments(url, table, uid)
    })

    document.querySelectorAll('[data-new-comment-uri]').forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault()
        const table = event.target.getAttribute('data-table')
        const id = event.target.getAttribute('data-id')
        const newCommentUrl = event.target.getAttribute('data-new-comment-uri')
        CreateAndEditCommentModal.openModal(newCommentUrl, document.querySelector('#widget-contentPlanner--comment-list'), table, id)
      })
    })
  }

  getFilterValues() {
    const filterForm = document.querySelector('form#widget-contentPlanner--comment-filter')
    if (!filterForm) {
      console.warn('Filter form not found')
      return null
    }
    const formData = new FormData(filterForm)
    return Object.fromEntries(formData.entries())
  }

  loadComments(url, table, uid) {
    if (!url || !table || !uid) {
      console.warn('Missing parameters for loading comments:', {url, table, uid})
      return
    }

    const filterValues = this.getFilterValues()
    let queryArguments = {table, uid, ...(filterValues || {})}
    new AjaxRequest(url)
      .withQueryArguments(queryArguments)
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
        CommentsResolvedItem.initEventListeners()
        CommentsDeleteItem.initEventListeners()
        this.initEventListeners()
      })
      .catch((error) => {
        console.error('Failed to load comments:', error)
        top.TYPO3.Notification.error('Error', 'Failed to load comments.')
      })
  }
}

export default new CommentsReloadContent()
