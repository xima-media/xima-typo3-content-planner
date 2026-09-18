/**
* Module: @content-planner/comment-mention
*
* Wires @-mentions (#305) into the comment composer (CP-28, #327) - the piece that connects the
* two feature stacks: MentionController::suggestAction() already serves the suggestion feed and
* MentionUtility already parses the persisted markers, but nothing produced those markers
* through the UI.
*
* Registered through Configuration/RTE/Comments.yaml's `importModules`; the record context the
* feed needs is injected into the editor configuration by CommentEditorConfigurationFactory.
*
* The up/downcast converters deliberately replace CKEditor's own mention markup: it writes
* `<span class="mention" data-mention="...">`, but RteHtmlParser restricts `<span>` to a fixed
* attribute list on the way into the database (see RteHtmlParser::getKeepTags()), so the uid
* would not survive the first save. On `<a>` it keeps every attribute, which is why
* MentionUtility's storage contract is built on an anchor.
*/
import AjaxRequest from "@typo3/core/ajax/ajax-request.js"
import { Plugin } from "@ckeditor/ckeditor5-core"
import { Mention } from "@ckeditor/ckeditor5-mention"

// Mirrors MentionUtility::MARKER_CLASS / ::MARKER_ATTRIBUTE - the two halves of the storage
// contract have to agree on these literally.
const MARKER_CLASS = "ctp-mention"
const MARKER_ATTRIBUTE = "data-mention-uid"

export class ContentPlannerMention extends Plugin {
  static get pluginName() {
    return "ContentPlannerMention"
  }

  static get requires() {
    return [Mention]
  }

  /**
   * `mention.feeds` has to hold a callback, so unlike the rest of the composer configuration it
   * cannot come from the YAML preset. CKEditor5 constructs every plugin before calling init()
   * on any of them, so setting it from a constructor still lands before MentionUI reads it.
   */
  constructor(editor) {
    super(editor)

    const context = editor.config.get("contentPlannerMention") || {}

    editor.config.set("mention.feeds", [{
      marker: "@",
      feed: query => fetchSuggestions(context, query),
    }])
  }

  init() {
    const editor = this.editor

    editor.conversion.for("downcast").attributeToElement({
      model: "mention",
      view: (modelAttributeValue, {writer}) => {
        // Anything without a backend user uid is not one of ours - leave it to CKEditor's
        // default converter rather than writing a marker MentionUtility would then ignore.
        if (!modelAttributeValue?.userUid) {
          return
        }

        return writer.createAttributeElement("a", {
          class: MARKER_CLASS,
          "data-mention": modelAttributeValue.id,
          [MARKER_ATTRIBUTE]: String(modelAttributeValue.userUid),
        }, {
          // CKEditor's own per-mention uid, not the backend user's: it is what keeps two
          // adjacent mentions of the same person from being merged into one element.
          id: modelAttributeValue.uid,
          priority: 20,
        })
      },
      converterPriority: "high",
    })

    editor.conversion.for("upcast").elementToAttribute({
      view: {
        name: "a",
        classes: MARKER_CLASS,
        attributes: {[MARKER_ATTRIBUTE]: true},
      },
      model: {
        key: "mention",
        value: viewItem => {
          const userUid = Number.parseInt(viewItem.getAttribute(MARKER_ATTRIBUTE), 10)
          if (!userUid) {
            return
          }

          return editor.plugins.get("Mention").toMentionAttribute(viewItem, {
            // A marker written through the API - the only way a mention could exist before
            // this plugin - carries no data-mention, so fall back to the visible text.
            id: viewItem.getAttribute("data-mention") || viewItem.getChild(0)?.data,
            userUid,
          })
        },
      },
      converterPriority: "high",
    })
  }
}

/**
 * @returns {Promise<Array<{id: string, text: string, userUid: number}>>}
 */
async function fetchSuggestions(context, query) {
  const url = TYPO3.settings.ajaxUrls?.ximatypo3contentplanner_mentions_suggest
  if (!url || !context.table || !context.uid) {
    return []
  }

  try {
    const response = await new AjaxRequest(url)
      .withQueryArguments({table: context.table, uid: context.uid, term: query})
      .get()
    const resolved = await response.resolve()

    return (resolved.result || []).map(user => ({
      // `id` is CKEditor's own requirement (it must start with the marker); `text` is both the
      // dropdown label and what gets inserted. The backend user uid travels under its own key
      // because CKEditor overwrites `uid` with an internal one.
      id: user.id,
      text: `@${user.name}`,
      userUid: user.uid,
    }))
  } catch (error) {
    // A failed lookup closes the dropdown - the comment itself stays editable, so this is not
    // worth interrupting the author with a flash message.
    console.error("Failed to load mention suggestions:", error)

    return []
  }
}
