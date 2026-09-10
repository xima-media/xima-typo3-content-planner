..  include:: /Includes.rst.txt

..  _assignee:

=======================
Assignee
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

For every content planner record, an assignee can be set. The assignee is the person responsible for the record.

..  note::
    If the :ref:`auto assignment <extconf-autoAssignment>` feature is enabled, the current user will be set as assignee when a new status is set to the record.

..  figure:: /Images/assignee.gif
    :alt: Assignee Screencast Content Planner


Only users with admin rights and the necessary :ref:`permission <permissions>` can be selected.

..  note::
    If the :ref:`current assignee highlight <extconf-currentAssigneeHighlight>` feature is enabled, records assigned to the current user will be highlighted in a light yellow color.

..  figure:: /Images/assignee-current.jpg
    :alt: Current assignment highlight

    Current assignment highlight

Selection
========

..  versionadded:: 1.5.0

        `Feature: #88 - Introduce user selection modal <https://github.com/xima-media/xima-typo3-content-planner/pull/88>`__

By clicking the assignee field in the header bar, a selection dialog will open. This dialog allows you to select a user as assignee for the record. The dialog will show all users with admin rights and the necessary :ref:`permission <permissions>`.

..  figure:: /Images/assignee-selection.jpg
    :alt: Select an assignee

    Select an assignee

..  versionadded:: 3.0.0

        `Feature: #325 - Redesign the assignee selection modal <https://github.com/xima-media/xima-typo3-content-planner/issues/325>`__

The list can be filtered by typing into the search field above it. Once an entry is highlighted or clicked, use the "Assign" button to confirm the change - selecting an entry does not assign it immediately. The list is fully keyboard operable:

..  csv-table::
    :header: "Key", "Action"

    ":kbd:`↑` / :kbd:`↓`", "Move the highlighted entry up or down"
    ":kbd:`Home` / :kbd:`End`", "Jump to the first or last visible entry"
    ":kbd:`Enter` / :kbd:`Space`", "Mark the highlighted entry as the pending selection"
    ":kbd:`Tab`", "Move to the search field, list, or action buttons"

The currently assigned user is marked with a highlighted ring around their avatar and a checkmark badge, not by colour alone.

Shortcuts
========

Use the shortcuts beneath the select field for "Assign to me" and "Unassign" to quickly change the assignee.

..  figure:: /Images/assignee-shortcuts.jpg
    :alt: Assignment shortcuts

    Assignment shortcuts

Edit Form
========

The assignee can be selected in the edit form of the record in the "Content Planner" tab.

..  figure:: /Images/assignee-edit.png
    :alt: Change the assignee of a record
    :class: with-shadow

    Change the assignee of a record

