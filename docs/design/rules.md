# Design rules

These rules are binding for every change to the Content Planner backend UI: the page module, record modal, record and file lists, Records module, dashboard widgets, toolbar notifications, status module, context menu and the email digest. When a change needs to break one, change the rule here first, in the same pull request, and say why.

The screens these rules produce are listed in [screens.md](screens.md).

## Principles

1. **Core stays core.** Content Planner only styles what it renders itself. It never restyles or restructures TYPO3 core UI. Drawings mark the extension's parts with a dashed outline, everything else is core and stays as TYPO3 renders it.
2. **Native first, modern in the details.** Reuse core components and tokens: callouts, badges, nav tabs, dropdown buttons, tables, form controls, the notification API. The result looks like TYPO3, only more current.
3. **Statuses are configuration, not code.** Title, colour and icon are set per installation. No template, label, KPI or test assumes a specific status such as "Needs review".
4. **Text always accompanies colour.** A status, an assignment or an unread mark is never carried by colour alone.
5. **Show what has a value.** Counts appear when they are above zero. One place per job: the doc header group is the page's status surface, the callout appears only when there is something to do.
6. **Calm by default.** Nothing moves, flashes or notifies unless something changed.

## What the extension may change

| Surface | Owner | Content Planner may |
| --- | --- | --- |
| Page tree | Core | Add a status label (Label API, so the status name is text) and the comment or to-do status information. Nothing else |
| Module menu, module frame, breadcrumb | Core | Nothing |
| Doc header | Core structure | Add one button group |
| Content element header | Core | Add the status strip as its own row above it |
| Modal frame | Core (`Modal.advanced`, size large) | Set the title text, the content and the footer buttons |
| Tables in list modules | Core | Add a status edge to the first cell and a status dropdown to the action column |
| Dashboard frame and widget chrome | Core | Fill the widget content |
| Toolbar item | Core | Fill the bell content and the dropdown |
| Form engine | Core | Add the Content Planner tab, item labels and field descriptions |
| Context menu | Core | Add one submenu |

## Colour

All colours come from tokens with a light and a dark value, through `light-dark()`. No literal colours in templates or CSS rules.

### Status colours

The seven stored palette keys stay: black, red, yellow, green, blue, purple, orange. Their base values live in `Resources/Public/Css/_variables.css`. Every visible tone is derived from the configured base, never picked by hand and never a fixed alpha over the base.

```css
.content-planner-status {
    --cp-base: var(--content-planner-color-green);
    --cp-tint: light-dark(
        color-mix(in srgb, var(--cp-base) 15%, white),
        color-mix(in srgb, var(--cp-base) 20%, var(--typo3-surface-base))
    );
    --cp-text: light-dark(
        color-mix(in srgb, var(--cp-base) 45%, black),
        color-mix(in srgb, var(--cp-base) 35%, white)
    );
    --cp-edge: light-dark(
        color-mix(in srgb, var(--cp-base) 60%, black),
        color-mix(in srgb, var(--cp-base) 75%, white)
    );
    --cp-border: color-mix(in srgb, var(--cp-base) 45%, white);
}
```

| Role | Used for | Light | Dark |
| --- | --- | --- | --- |
| `tint` | Badge and strip background | 15 % base on white | 20 % base on the surface |
| `text` | Status name | 45 % base, rest black | 35 % base, rest white |
| `edge` | Dot, flag icon, 3 and 4 px edges | 60 % base, rest black | 75 % base, rest white |
| `border` | Badge outline | 45 % base, rest white | none |

Reference output for the seven keys, measured on white and on `#1f1f23`:

| Key | tint | text | edge | border | dark tint | dark text | dark edge |
| --- | --- | --- | --- | --- | --- | --- | --- |
| black | `#eef1f3` | `#414a4e` | `#566268` | `#cdd6db` | `#363a3f` | `#d8dfe3` | `#acbbc2` |
| red | `#feedef` | `#703d42` | `#965258` | `#fdc9ce` | `#4b3439` | `#fdd5d9` | `#fba6ae` |
| yellow | `#fff8ea` | `#735c35` | `#997b46` | `#ffe8c1` | `#4c4233` | `#ffeecf` | `#ffda98` |
| green | `#e9f0ea` | `#304733` | `#405f44` | `#bcd3bf` | `#2e3833` | `#cbddcd` | `#8fb694` |
| blue | `#e8f5f7` | `#2d545a` | `#3c7078` | `#b9e0e6` | `#2d3e44` | `#c9e7ec` | `#8bccd6` |
| purple | `#e7e9f6` | `#293056` | `#374073` | `#b6bce3` | `#2b2e42` | `#c6cbe9` | `#8590d0` |
| orange | `#ffeae3` | `#73321e` | `#994328` | `#ffbfaa` | `#4c2f29` | `#ffcdbd` | `#ff9472` |

- Text on tint reaches 6.0 to 10.5:1 in the light scheme and at least 8.3:1 in the dark scheme. Edges against white and tint reach 3.8 to 9.8:1, and 5.4 to 12.3:1 on the dark surface.
- The raw base colours are not safe as dot, edge or text colour: five of the seven fall below 3:1 on white (yellow reaches 1.5:1), six of the seven on their own 20 % tint.
- Status colours differ in lightness as well as hue.

### Other colours

| Role | Light | Dark |
| --- | --- | --- |
| Accent, primary action, focus ring | Core primary token (`#5033c7` in the Fresh theme) | Lighter violet `#9d8cf0`, `#b9a8ff` on a primary tint |
| Muted text | `#5f5f66`, in code `color-mix(in srgb, currentColor, transparent 35%)` | The same mix, never a fixed grey |
| Neutral tag | `#f1f0f5` on `#3a3a40` | Core surface container tokens |
| "Assigned to you" tag | `#ece9f8` on `#2d1f73` | Primary tint, mixed like a status colour |
| Info callout | Core `callout-info` | Core |

- `#5033c7` as text or ring on a dark surface reaches only about 2:1. Use the core primary token, which core already switches, or the lighter violet above.
- The toolbar badge is the core `toolbar-item-badge badge-pill` and reaches 5.3:1.

## Typography and size

- Inherit the core font and size (Verdana stack, 12px base). No custom fonts, no icon fonts.
- 11px is the smallest text. Secondary text is 11px, body 12px, section titles 12 to 14px, page titles use the core H1.
- Counts use tabular digits.
- Icons in extension-rendered rows are 12 to 16px. Icons in doc header buttons are core `IconSize::SMALL`, so a button stays at the core height of 29 to 30px. The default size makes buttons about 44px high and wraps the button row.

## Status presentation

| Element | Spec |
| --- | --- |
| Badge | 22px high, 18px in dense lists and the page tree, pill radius 11px, 1px `border`, `tint` background, configured status icon plus the name in `text`, bold 11px |
| Strip on a content element | Own row above the core element header, 34px high, 3px top edge in `edge`, `tint` background, icon and name in bold `text`, no "Status:" prefix |
| Row or tile edge | 4px inset box shadow on the first cell, or 3px on the top of a tile. No tint on the row, no tinted tile |
| Dot in the drawings | Stands in for the configured status icon. In the product the icon is rendered, because it carries the meaning once colours repeat |
| No status | Nothing in tiles and on elements, a muted "Set status" in lists |

- The strip shows meta only when it has a value: to-dos as `0/2`, comments as a count, an avatar when assigned, a muted "Assign" when not. Everything else sits in its overflow menu.
- Avatars appear in extension-rendered rows only, always with the name available as text for assistive technology.

## Doc header group

One group of three buttons, in this order:

| Button | Label | Name for assistive technology |
| --- | --- | --- |
| Status | Status icon and name, with a dropdown | "Status: Completed, change" |
| Assignee | User icon and display name. Unassigned reads "Unassigned", the CLI user reads "System" | "Assignee: Anna Schmidt" |
| Comments | Comment icon and count | "1 comment" |

- Core buttons render icon and text only. No avatar, no count pill.
- Build the assignee and comment buttons with `GenericButton`, which keeps label and title apart. `LinkButton` prints its title as the label.
- Push the three buttons as one group array. Core renders an empty `aria-label` on the group, so the group cannot carry a name.
- The page module has no reload button in v14 and the drawings do not show one.

```php
$icon = $this->iconFactory->getIcon('content-planner-user-circle', IconSize::SMALL);
```

### Header display modes

| Mode | Renders |
| --- | --- |
| `chip` (default) | The doc header group. Above the columns a core callout appears only while the page has open to-dos |
| `docked` | One compact bar in the doc header button row: status name, assignee, comments, to-dos as text |
| `banner` (legacy) | The full-width banner, slimmed: no "Status:" prefix, buttons with text, recipe colours |

All modes use the same colour recipe and the same order of information.

## Record modal

- The frame is core: `Modal.advanced` with `Modal.sizes.large`. The frame is a fixed 800px high, so panes flex to the full height and never size to their content.
- The title is plain text: `Content Planner: Home`. A badge cannot sit in it, so the context row below carries the record, its type and the status dropdown.
- Tabs are left-aligned core `nav-tabs` with counts: Comments, Assignee, Watchers. `role="tablist"`, `aria-controls` on each tab, one tab stop with arrow keys.
- Every tab uses the whole area as two columns: the main task on the left, context on the right. Below about 760px the columns stack, the context comes first and the tab bar scrolls sideways.
- Footer: "Edit record", then "Close", both `btn-default`. Core marks only "Close" as the focused default.

### Comments

- Filters are visible in the right column: Open, With open to-dos, Resolved, All, "Include comments on content elements", sort.
- Checklists toggle in place. Replies are collapsed. Resolved comments collapse into one line. Older comments load on demand, 20 at first.
- The composer sits at the bottom. Toolbar: bold, italic, link, to-do list, mention. No source, subscript or superscript. Ctrl + Enter sends.
- The status select in the composer is shown only to users who may change the status and lists only statuses they may set.
- "Show to-dos" in the page callout opens this tab with the to-do filter.

### Assignee

- Left: an "Assign to me" button, a search field, the list of users. Right: the current assignee with "since date, by name" and a card that says what an assignment does.
- Picking a user assigns at once and notifies them. There is no separate "Assign" button and no undo, because the notification is already sent. A wrong pick is corrected by picking again.
- "Unassigned" stays both as a list option and as an "Unassign" button.
- "Assign to me" sits outside the listbox. The listbox has one tab stop, arrow keys, `aria-activedescendant`.
- Variants are explained, not only disabled: "You can assign yourself only", and a read-only view with no list.
- No match for a search says so and says who is listed.

### Watch

| State | Card title | Reason line | Button |
| --- | --- | --- | --- |
| Not watching | You're not watching | You won't get notifications for this record. | Watch |
| Watching automatically | Watching automatically | Added by an assignment, comment or status change. | Mute |
| Watching | Watching | You added yourself. | Mute |
| Muted | Muted | You won't be added again automatically. | Watch again |

- "Mute" is the sticky unwatch. It always wins over later automatic triggers until the user picks "Watch" again.
- The button names the action, never the state, and does not use `aria-pressed`.
- Right: "Watchers · N" with the reason per person (Automatic, Manual). People the viewer may not see count in N and appear as "1 more person you can't see".
- An empty list says who is added and when.

## Notifications

### Toolbar

- The bell and its badge are core. The badge counts unread entries.
- The dropdown is not wider than the core toolbar menu (350px). A `min-width` above that causes a horizontal scrollbar, also when the list is empty.
- One sentence per entry: actor, verb, record. Below it time and page path. A type icon on the left.
- Unread is a dot and bold text. Entries are grouped "Today" and "Earlier".
- A reason tag says why the entry exists. "Mentioned" and "Assigned to you" are highlighted, "Watching" is neutral.
- On hover and focus an entry shows "Mark as read" and "Mute". Mute is the same sticky unwatch as in the modal.
- A filter switches between "All" and "For you" (mentions and assignments).
- The footer links to the email digest setting. The empty state reads "You're all caught up".
- A click opens the record with the modal on the matching tab.

### Email digest

- Sent as core `FluidEmail` with the core `SystemEmail` layout, so it carries the site name and the TYPO3 mail frame. The template does not bring its own `<html>` and `<body>`.
- Per record: linked title, status as a text badge, the reason on the right, the changes as a short list.
- The footer says "Mute", the word the UI uses.

## Lists and overviews

### Record list and file list

- The core table is untouched. A status edge on the first cell, never a row or tile tint. Leave the empty action slots of core alone.
- The action column gets a status dropdown with the status name as text. Without a status it reads "Set status" in muted text.
- Folder status sits in the same doc header group as page status. The folder banner stays for `banner` mode only.

### Records module

- Quick filters are toggle chips with a label, a count and `aria-pressed`: "Assigned to me", "Open to-dos", "Open comments". The labels never change with the state.
- Filters are inline, not behind a "More filters" modal.
- A record shows its page path as a second line, so same-named records are distinguishable. A column "Activity" carries the comment count. The sort column has `aria-sort`.
- "Assigned to you" is a tag in the title cell. The pagination is visible.

### Dashboard

- The dashboard frame and widget chrome are core.
- The KPI row is independent of any status: Assigned to me, Open to-dos, Open comments. These counts already exist per user and permission-aware as the Records presets, so reuse them. A status tile is the existing configurable status widget, where the editor picks the status. This widget exists on v14 only.
- The status table shows the status as a badge, the assignee as avatar and display name, the page path as a second line instead of a site column, and the comment count on the right.
- Recent activity is one line per event. Records assigned to the viewer carry the "Assigned to you" tag, never a beige row. The CLI user reads "System".
- The overview legend shows the count per status. The chart itself stays the core chart.

### Status module

- A short intro says what statuses are, that their order is the order in every status menu and that new records start with the default status.
- Each row shows a badge preview. "Default" and "Hidden" are values, not empty columns. Hidden means deactivated: the status stays on existing records and is no longer offered.
- Row actions are one button group. The empty state is a callout with one action, "New status".

## Forms, context menu, mention card

- The Content Planner tab uses the words of the modal: "No status" and "Unassigned". Field descriptions say what a change triggers, for example that watchers are notified.
- A record whose assignee can no longer be assigned keeps that person selected. The form never shows "Missing label".
- The context menu has one submenu labelled with the current status, "Status: In progress". Its items are radio items with the configured icon, only statuses the user may set, then "Change with comment…" and "Remove status".
- The mention card shows avatar, name, username and email, plus one line whether the person is watching and so will be notified. The mention chip uses the primary tint, not an orange outline.

## Dark mode, workspaces, languages

- Every Content Planner colour works in both schemes through `light-dark()`. Core sets `color-scheme` on the root.
- In a workspace, **status changes always apply live**. The status dropdown, context menu and modal write to the live record, the Content Planner fields in the form are read-only, and the modal says "Status changes apply live, not to the workspace version". Notifications fire when the change is saved.
- Each translation carries its own status. Watchers follow the default language record.
- In narrow language columns the strip keeps the status icon and name, truncated with the full name in its title. Counts and the assignee move into its overflow menu.

## Terminology

| Use | Not |
| --- | --- |
| Unassigned | Not assigned, -- Not assigned -- |
| No status | stateless |
| Assigned to you | Assigned to me (except as a filter or KPI name) |
| System (the CLI user) | `_cli_` |
| Open comments | Unresolved comments |
| to-dos | TODOs, checklist items |
| Watchers · 3 | Watchers (3) |
| Watch, Mute, Watch again | Stop watching, Unwatch |
| Close | Cancel (for a modal that only closes) |
| Show to-dos | Show |

## Accessibility

- Text reaches 4.5:1, large text and non-text elements reach 3:1. Measure with a script, do not estimate.
- Every status carries text. Icon-only buttons have an `aria-label`. Counts carry a text name such as "0 of 2 to-dos done".
- Controls are at least 24px. List options are 40 to 44px.
- Tabs, listboxes and toggles follow the APG patterns. A button that names an action does not also use `aria-pressed`.
- One shared `:focus-visible` style: 2px ring in the primary token, 2px offset. Composer focus uses `:focus-within` on its wrapper.
- Fields have a visible label or an `aria-label`. The sorted column has `aria-sort`. Information that appears without a click is announced through the notification API or an `aria-live` region.

## Compatibility and stability

- Everything works on TYPO3 v13 and v14. A v14-only feature, such as the configurable status widget, is registered conditionally and has a v13 fallback or none.
- Keep the data attributes and class names that the Playwright tests use: `.content-planner-header`, `.content-planner-header__body`, `button.content-planner-link--comments`, `[data-content-planner-assignees]`, `button[name="close"]`, `[data-assignee-search]`, `[data-assignee-listbox]`, and the tab ids `content-planner-tab-*`.
- `HeaderInfo.html`, `Assignees.html`, `Watch.html` and the RTE preset `Comments` can be overridden by integrators. A change to them goes in the release notes.
- A change to what `chip`, `docked` or `banner` render is a breaking change for integrators.

## Pull request checklist

- [ ] Only extension-rendered UI changed. No core layout restyled
- [ ] No literal colours. Status tones derive from the configured colour
- [ ] Every status shows its name as text, light and dark
- [ ] Contrast measured: 4.5:1 for text, 3:1 for graphics
- [ ] Words match the terminology table
- [ ] Modal tabs use the full area at large size
- [ ] Works on v13 and v14, light and dark
- [ ] Playwright selectors untouched, or updated in the same change
- [ ] A changed screen is re-exported to `screens/` and this file is updated if a rule moved
