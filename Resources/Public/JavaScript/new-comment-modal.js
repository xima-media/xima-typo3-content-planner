/**
 * Module: @xima/ximatypo3contentplanner/new-comment-modal
 */
import Modal from "@typo3/backend/modal.js";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Viewport from "@typo3/backend/viewport.js";


class NewCommentModal {

  constructor() {
    const item = document.querySelector('#new-comment-modal');
    if (item) {
      const pid = item.getAttribute('data-pid');
      const userid = item.getAttribute('data-userid');
      item.addEventListener('click', e => {
        e.preventDefault()
        Modal.advanced({
          title: 'New Comment',
          content: document.createRange()
            .createContextualFragment('<textarea id="new-comment-message" class="form-control t3js-formengine-textarea formengine-textarea" rows="4" cols="50" style="width:100%;height:100%;"></textarea>'),
          size: Modal.sizes.small,
          staticBackdrop: true,
          callback: function(modal) {
            modal.querySelector('#new-comment-message').focus();
            // todo: initialize ckeditor
          },
          buttons: [
            {
              text: TYPO3.lang['button.modal.footer.save'],
              name: 'save',
              icon: 'actions-save',
              active: true,
              btnClass: 'btn-primary',
              trigger: function (event, modal) {
                const message = modal.querySelector('#new-comment-message').value;

                // need to add existing comment uids to datahandler to not overwrite them
                // todo: simplify
                const commentUids = document.querySelectorAll('*[data-comment-uid]');
                // aggregate comment uids and implode them with comma
                let commentUidString = '';
                commentUids.forEach((commentUid, index) => {
                  commentUidString += commentUid.getAttribute('data-comment-uid');
                  if (index < commentUids.length - 1) {
                    commentUidString += ',';
                  }
                });

                new AjaxRequest(top.TYPO3.settings.RecordCommit.moduleUrl)
                  .post({
                    data: {
                      tx_ximatypo3contentplanner_comment: {
                        NEW_1719856625: {
                          content: message,
                          author: userid,
                          foreign_table: 'pages',
                          foreign_uid: pid,
                          pid: pid
                        }
                      },
                      pages: {
                        [pid]: {
                          tx_ximatypo3contentplanner_comments: 'NEW_1719856625' + (commentUidString ? ',' + commentUidString : '')
                        }
                      }
                    }
                  })
                  .then(function (result) {
                    if (result.response.ok) {
                      top.TYPO3.Notification.success('Status', 'Comment successfully added.');
                      Viewport.ContentContainer.refresh();
                    } else {
                      top.TYPO3.Notification.error('Status', 'Error creating comment.');
                    }
                  });
                modal.hideModal();
              }
            },
            {
              text: TYPO3.lang['button.modal.footer.close'],
              name: 'close',
              icon: 'actions-close',
              active: true,
              btnClass: 'btn-secondary',
              trigger: function (event, modal) {
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
