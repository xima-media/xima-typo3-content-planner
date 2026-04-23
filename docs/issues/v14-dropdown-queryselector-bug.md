# Bug: Comment dropdown broken on TYPO3 v14

## Status: Fixed

## Problem

The comment action dropdown (Edit, Resolve, Share, Delete) does not open on TYPO3 v14. A JavaScript error prevents interaction.

## Error

```
dropdown.js:13 Uncaught SyntaxError: Failed to execute 'querySelector' on 'Document': '#' is not a valid selector.
    at f.getMenu (dropdown.js:13:3641)
    at HTMLDocument.<anonymous> (dropdown.js:13:3056)
```

## Root Cause (two issues)

### 1. querySelector error

TYPO3 v14's `dropdown.js` has a `getMenu()` method that reads the `href` attribute as a CSS selector:

```javascript
getMenu(e) {
  const r = e.getAttribute("href");
  return r?.startsWith("#") ? document.querySelector(r) : // <-- fails on href="#"
         e.nextElementSibling?.matches(".dropdown-menu") ? e.nextElementSibling : ...
}
```

When `href="#"`, `document.querySelector('#')` throws an uncaught `SyntaxError`. The fallback is never reached.

### 2. Popover API positioning

TYPO3 v14 completely replaced Bootstrap's Dropdown+Popper.js with the native **Popover API**. The `dropdown.js` converts all `data-bs-toggle="dropdown"` elements:
- Adds `popover` attribute to the `.dropdown-menu`
- Adds `popovertarget` to the toggle button

Popovers render in the browser's **top layer** (viewport-relative, not parent-relative). TYPO3 v14 positions them using **CSS Anchor Positioning**:

```css
.dropdown-toggle { anchor-name: --dropdown; }
.dropdown-menu   { position-anchor: --dropdown; position-area: block-end span-inline-end; }
```

Our toggle buttons were missing the `dropdown-toggle` class, so no anchor was established and the popover floated to the viewport's default position.

## Fix Applied

### Files changed

- **`Resources/Private/Templates/Default/Comments.html`**
  - Changed `<a href="#">` to `<button type="button">` (fixes querySelector error)
  - Added `dropdown-toggle dropdown-toggle-no-chevron` classes (enables CSS anchor positioning on v14)

- **`Resources/Public/Css/Comments.css`**
  - Updated `.content-planner-comment__actions > a` selector to `> button`
  - Removed manual `!important` positioning overrides (no longer needed)

### Why this works

- **v13:** Bootstrap's Dropdown class + Popper.js handles positioning. `dropdown-toggle` is the standard Bootstrap class for dropdown triggers.
- **v14:** The `dropdown-toggle` class gets `anchor-name: --dropdown` from TYPO3's backend CSS. The `.dropdown-menu` references this anchor via `position-anchor: --dropdown` and positions itself at `block-end span-inline-end` (below the button, aligned right).

## Reproduction

1. `ddev install 14`
2. Open any page with Content Planner status
3. Open the comments panel
4. Click the three-dot action button on any comment
5. ~~Observe: dropdown does not open~~ Dropdown opens and positions correctly relative to the button

Works correctly on both TYPO3 v13 and v14.

## Priority

Medium — v13 is the primary supported LTS version, v14 support is secondary.
