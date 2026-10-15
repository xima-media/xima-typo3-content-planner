# Screen designs

The reference designs for the Content Planner v3 backend UI. They live on a design canvas: https://claude.ai/artifact/75RRvicRMXgjAfmEar53M8. The canvas is private to its owner until it is shared from its Share menu.

The mockups are HTML drawings of the target. Their copy is English and is the target wording. The notes on the canvas are German. Names, counts and dates are examples. A dashed outline marks what Content Planner renders, everything else is TYPO3 core and stays as core renders it.

The rules behind these screens are in [rules.md](rules.md). The previews are the pages of the canvas PDF export (2026-10-08), one page per board. Click one for the full size.

## Page module and record modal

| Board | Screen | What it settles | Preview |
| --- | --- | --- | --- |
| 1 | Layout module | Doc header group, to-do callout, status strip per content element, an element without status, core page tree and menu untouched | <a href="screens/01-layout-module.png"><img src="screens/01-layout-module.png" width="220" alt="Board 1"></a> |
| 2 | Record modal | Clickable prototype of the three tabs in the fixed frame: assign, watch states, composer | <a href="screens/02-record-modal.png"><img src="screens/02-record-modal.png" width="220" alt="Board 2"></a> |
| 5 | Assignee tab | Two columns, "Assign to me", assign at once, search states, permission variants | <a href="screens/05-assignee-tab.png"><img src="screens/05-assignee-tab.png" width="220" alt="Board 5"></a> |
| 6 | Watch tab | Four watch states, watcher list with reasons, hidden watchers, empty list | <a href="screens/06-watch-tab.png"><img src="screens/06-watch-tab.png" width="220" alt="Board 6"></a> |
| 8 | Comments tab | Filter column, checklists, resolved line, composer, empty state, long thread | <a href="screens/08-comments-tab.png"><img src="screens/08-comments-tab.png" width="220" alt="Board 8"></a> |

## Lists, modules and overviews

| Board | Screen | What it settles | Preview |
| --- | --- | --- | --- |
| 3 | Status module | Intro, badge preview, default and hidden as values, action group, empty state | <a href="screens/03-status-module.png"><img src="screens/03-status-module.png" width="220" alt="Board 3"></a> |
| 7 | Record list | Core table untouched, status edge, status dropdown with the name as text | <a href="screens/07-record-list.png"><img src="screens/07-record-list.png" width="220" alt="Board 7"></a> |
| 9 | Dashboard | KPI row without fixed statuses, status table, activity feed, overview legend, effort per widget | <a href="screens/09-dashboard.png"><img src="screens/09-dashboard.png" width="220" alt="Board 9"></a> |
| 10 | Records module | Toggle chips with counts, inline filters, page path line, "Assigned to you" | <a href="screens/10-records-module.png"><img src="screens/10-records-module.png" width="220" alt="Board 10"></a> |
| 13 | File list | List and tile view with status edge and badge, folder status in the doc header | <a href="screens/13-file-list.png"><img src="screens/13-file-list.png" width="220" alt="Board 13"></a> |

## System surfaces and details

| Board | Screen | What it settles | Preview |
| --- | --- | --- | --- |
| 11 | Notifications | Toolbar dropdown with entries, empty state, "For you" filter | <a href="screens/11-notifications.png"><img src="screens/11-notifications.png" width="220" alt="Board 11"></a> |
| 12 | Edit form | Doc header group in one row, Content Planner tab labels and descriptions | <a href="screens/12-edit-form.png"><img src="screens/12-edit-form.png" width="220" alt="Board 12"></a> |
| 14 | Details | Context menu, mention card, email digest, the three header display modes | <a href="screens/14-details.png"><img src="screens/14-details.png" width="220" alt="Board 14"></a> |
| 15 | Variants | Dark scheme, narrow modal, language comparison view, workspaces | <a href="screens/15-variants.png"><img src="screens/15-variants.png" width="220" alt="Board 15"></a> |

## Building blocks

| Board | Component |
| --- | --- |
| 4 Components | Status strip variants, doc header group states, the derived colour recipe for the seven palette colours, dark samples |

![Components](screens/04-components.png)

## Updating the previews

When a board changes on the canvas, export the canvas as PDF and render the page again:

```bash
pdftoppm -r 110 -png -f 5 -l 5 "Content Planner v3 UI Review.pdf" page
mv page-05.png docs/design/screens/05-assignee-tab.png
```

Page numbers follow the board numbers above.
