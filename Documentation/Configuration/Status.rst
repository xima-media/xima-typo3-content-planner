..  include:: /Includes.rst.txt

..  _status:

=======================
Status
=======================

Default Status
==================

By default, there are four different statuses available:

- **Pending**: The page is not yet ready for editing.
- **In progress**: The page is currently being edited.
- **Needs review**: The page is ready for review.
- **Completed**: The page is ready to be published.

..  figure:: /Images/status-init.png
    :alt: Initial status selection
    :class: with-shadow

..  note::
    These statuses are only intended as an initial configuration. Customize the statuses according to your needs.

Custom Status
==================

Statuses are managed as records on the root page (pid 0).

..  figure:: /Images/status-default.png
    :alt: Default Status Records
    :class: with-shadow

    Default Status Records

You can add a new status, edit an existing status, change the status order or delete a status.

..  figure:: /Images/status-edit.png
    :alt: Edit Status Record
    :class: with-shadow

    Edit Status Record


.. t3-field-list-table::
    :header-rows: 1

    -
        :Field:
            Field:

        :Description:
            Description:

    -
        :Field:
            Title

        :Description:
            Title of the status.

    -
        :Field:
            Icon

        :Description:
            Select one of the existing icons as representative picture of the status.

    -
        :Field:
            Color

        :Description:
            Select one of the existing colors for this status.

..  _status-accessibility:

Accessibility: Colour Is Never the Only Signal
================================================

Content Planner never conveys a status through colour alone: every status is always
rendered as an icon plus its title text (in the doc header, the content element accent,
record lists, the file list and the backend widgets). Colour is a reinforcing cue, not the
carrier of information, so choosing a distinctive **icon** for each status matters at
least as much as choosing a distinctive colour, especially for colour-blind editors.

Contrast Guidance for the Built-in Colors
------------------------------------------

The built-in colors are calibrated as accents on top of icon and text, not as the sole
means of distinguishing a status. Measured against WCAG 2.1 contrast ratios (`relative
luminance formula <https://www.w3.org/TR/WCAG21/#dfn-relative-luminance>`__), several of
them fall short of the 3:1 minimum required for a non-text UI element (`SC 1.4.11
<https://www.w3.org/TR/WCAG21/#non-text-contrast>`__) when used as an icon fill on a
plain white surface:

..  t3-field-list-table::
    :header-rows: 1

    -
        :Color:
            Color

        :Hex:
            Hex

        :vs. white surface:
            vs. white surface

        :vs. dark surface:
            vs. dark surface

    -
        :Color:
            black
        :Hex:
            ``#90a4ae``
        :vs. white surface:
            2.6:1
        :vs. dark surface:
            6.6:1

    -
        :Color:
            blue
        :Hex:
            ``#64bbc8``
        :vs. white surface:
            2.2:1
        :vs. dark surface:
            7.7:1

    -
        :Color:
            green
        :Hex:
            ``#6a9e71``
        :vs. white surface:
            3.1:1
        :vs. dark surface:
            5.5:1

    -
        :Color:
            yellow
        :Hex:
            ``#ffcd75``
        :vs. white surface:
            1.5:1
        :vs. dark surface:
            11.6:1

    -
        :Color:
            red
        :Hex:
            ``#fa8893``
        :vs. white surface:
            2.3:1
        :vs. dark surface:
            7.3:1

    -
        :Color:
            purple
        :Hex:
            ``#5c6bc0``
        :vs. white surface:
            4.9:1
        :vs. dark surface:
            3.5:1

    -
        :Color:
            orange
        :Hex:
            ``#ff7043``
        :vs. white surface:
            2.7:1
        :vs. dark surface:
            6.2:1

..  note::
    This is exactly why colour is never the sole carrier: on a light background, five of
    the seven built-in colors alone would not reliably meet the non-text contrast
    minimum. Because every status is always paired with an icon shape and its title text,
    none of these numbers are a blocking issue for the extension itself - but they do mean
    you should not repurpose the raw color value for a new UI element (e.g. a plain
    coloured dot with no icon/text) without checking contrast yourself.

If you build your own display of Content Planner statuses (e.g. a custom widget or a
frontend rendering), apply the same rule the extension follows internally:

-   Never use colour as the only way to distinguish a status. Always pair it with an icon
    and/or the status title as real text (not only inside a `title` attribute, which is
    not reliably exposed to assistive technology, see :ref:`aria-icon-controls
    <status-aria-icon-controls>` below).
-   For any text rendered on top of one of these colors (e.g. a badge), keep at least a
    4.5:1 contrast ratio for normal-size text (3:1 for large/bold text), per `WCAG SC 1.4.3
    <https://www.w3.org/TR/WCAG21/#contrast-minimum>`__. Dark text (close to black) clears
    4.5:1 on six of the seven built-in colors. Purple is the exception: black on purple
    reaches only 4.32:1, so use white text there instead, which reaches 4.86:1.
-   For a non-text element such as an icon or a status dot, keep at least a 3:1 contrast
    ratio against its immediate background, per `WCAG SC 1.4.11
    <https://www.w3.org/TR/WCAG21/#non-text-contrast>`__.

..  _status-aria-icon-controls:

Icon-only Controls Need an Accessible Name
--------------------------------------------

A `title` attribute on an icon is not sufficient on its own: TYPO3 core always marks
icons `aria-hidden="true"` (see ``TYPO3\CMS\Core\Imaging\Icon``), so a `title` set only on
the icon element never reaches assistive technology. Any icon-only control (a button or
link whose only content is an icon, with no visible text next to it) needs an explicit
`aria-label` on the control itself, or a visually-hidden text node inside it - not only a
`title` on the icon.
