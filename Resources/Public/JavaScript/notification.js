/**
* Module: @xima/ximatypo3contentplanner/message
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"

class Notification {
  constructor() {
  }

  message(message, resultStatus) {
    new AjaxRequest(TYPO3.settings.ajaxUrls.ximatypo3contentplanner_message)
      .withQueryArguments({
        message: message,
        resultStatus: resultStatus
      })
      .get()
      .then(async result => {
        if (result.response.ok) {
          const data = await result.response.json();
          switch (data.severity) {
            case 2:
              top.TYPO3.Notification.error(data.title, data.message);
              break;
            case 1:
              top.TYPO3.Notification.warning(data.title, data.message);
              break;
            case 0:
              top.TYPO3.Notification.success(data.title, data.message);
              break;
            case -1:
              top.TYPO3.Notification.info(data.title, data.message);
              break;
            case -2:
              top.TYPO3.Notification.notice(data.title, data.message);
              break;
            default:
              console.warn('Unknown notification severity:', data.severity);
          }
        } else {
          top.TYPO3.Notification.error('Error', 'Failed to fetch notification message.');
        }
      }).catch(error => {
          // The request was likely aborted by a content frame refresh (e.g. Firefox NS_BINDING_ABORTED).
          // The underlying action already completed, so the missing notification toast is non-critical.
          console.debug('Content Planner: notification message request did not complete:', error);
      });
  }
}

export default new Notification()
