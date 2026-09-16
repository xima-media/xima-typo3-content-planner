/**
* Module: @content-planner/comment-todo-toggle
*
* Makes to-do checkboxes inside a comment's rendered content clickable directly from the
* display state (CP-30, #389), instead of requiring the composer to be opened first. CKEditor's
* saved to-do markup renders checkboxes as `disabled` outside the editor (see
* PlannerUtility::generateTodoForComment()); this module lifts that restriction for comments the
* current user is allowed to edit (data-todo-editable="true", set by the Comment.html partial
* from CommentItem::getCanCurrentUserEdit()) and persists the toggle through
* commentToggleTodoAction - the same DataHandler-routed write path the composer uses.
*
* Checkboxes are never disabled while a request is in flight: a disabled control drops focus
* to <body> the instant a keyboard user presses Space on it, losing their tab position. Instead,
* toggle requests for the same comment are queued and run one after another - the server flips
* one checkbox by re-parsing and rewriting the comment's whole `content` string, so two
* overlapping requests for the same comment would race and one could silently clobber the other.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import Notification from "@content-planner/notification.js"

class CommentTodoToggle {
  constructor() {
    /** @type {Map<string, Promise<void>>} */
    this.queues = new Map()

    window.addEventListener('typo3:contentplanner:reinitializelistener', () => {
      this.initEventListeners()
    })
  }

  initEventListeners() {
    document.querySelectorAll('[data-todo-editable="true"]').forEach(container => {
      // Indexed over every checkbox in the comment, not just the ones bound below - this must
      // match TodoToggleUtility::toggle() server-side, which parses the whole saved `content`
      // string. A checkbox hand-authored outside .todo-list (CKEditor's source editing toolbar)
      // still occupies an index slot there, so skipping it here without counting it would shift
      // every later index out of sync with the server.
      Array.from(container.querySelectorAll('input[type=checkbox]')).forEach((checkbox, index) => {
        if (!checkbox.closest('.todo-list') || 'true' === checkbox.dataset.todoToggleBound) {
          return
        }
        checkbox.dataset.todoToggleBound = 'true'
        checkbox.disabled = false
        checkbox.addEventListener('change', () => this.toggle(container, checkbox, index))
      })
    })
  }

  toggle(container, checkbox, index) {
    const commentUid = container.closest('[data-comment-uid]')?.getAttribute('data-comment-uid')
    if (!commentUid) {
      return
    }

    const checked = checkbox.checked

    this.enqueue(commentUid, () => new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_commenttodotoggle)
      .post({commentUid, todoIndex: index, checked: checked ? 1 : 0})
      .then(async result => {
        const resolved = await result.resolve()
        if (!result.response.ok || resolved.error) {
          throw new Error(resolved.error || 'Content Planner: to-do toggle request failed')
        }
      })
      .catch(error => {
        console.error('Content Planner: failed to toggle to-do item:', error)
        checkbox.checked = !checked
        Notification.message('comment.todo', 'failure')
      }))
  }

  /**
   * Chains a request after whatever is currently pending for the same comment, so requests for
   * one comment never run concurrently. Each request already handles its own errors (see
   * `.catch()` above), so the chain itself never rejects and later toggles are never blocked by
   * an earlier failure.
   *
   * @param {string} commentUid
   * @param {() => Promise<void>} request
   */
  enqueue(commentUid, request) {
    const next = (this.queues.get(commentUid) || Promise.resolve()).then(request, request)
    this.queues.set(commentUid, next)
    next.finally(() => {
      if (this.queues.get(commentUid) === next) {
        this.queues.delete(commentUid)
      }
    })
  }
}

export default new CommentTodoToggle()
