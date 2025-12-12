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

Shortcuts
========

Use the shortcuts beneath the select field for "Assign to me" and "Unassign" to quickly change the assignee.

..  figure:: /Images/assignee-shortcuts.jpg
    :alt: Assignment shortcuts

    Assignment shortcuts

Edit Form
========

The assignee can be select in the edit form of the record in the "Content Planner" tab.

..  figure:: /Images/assignee-edit.png
    :alt: Change the assignee of a record
    :class: with-shadow

    Change the assignee of a record

