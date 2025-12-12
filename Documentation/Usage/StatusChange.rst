..  include:: /Includes.rst.txt

..  _status-change:

=======================
Status Change
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

They are several ways to change the status of a page or a record.

Page Tree
******************

..  figure:: /Images/change-tree.png
    :alt: Change page status in the page tree
    :class: with-shadow

    Change page status in the page tree

In the page tree, you can change the status of a page via the context menu.

Module Header
******************

..  figure:: /Images/change-header.png
    :alt: Change page status in the module header
    :class: with-shadow

    Change page status in the module header

In the module header of the modules :code:`web_layout`, :code:`web_list`, :code:`record_edit` and :code:`file_list`, you can change the status of a page via the status dropdown.

Edit Form
******************

..  figure:: /Images/change-settings.png
    :alt: Change page status in the edit form
    :class: with-shadow

    Change page status in the edit form

In the edit form of a record, you can change the status in the "Content Planner" tab.

Record List
******************

..  figure:: /Images/records.jpg
    :alt: Change page status in the record list
    :class: with-shadow

    Change page status in the record list

In the record list, you can change the status of a record via the dropdown.

..  note::
    These option is only available for additional database records with status behavior.

..  figure:: /Images/records.gif
    :alt: Records Screencast Content Planner

Update multiple records
==================

..  versionadded:: 1.3.0

        `Feature: #50 - Add table header bulk status update action <https://github.com/xima-media/xima-typo3-content-planner/pull/50>`__

Within the record list, you can also change the status of multiple records at once.

Select the records you want to change the status of (or "Check all") and use the status dropdown in the table header.

..  figure:: /Images/change-bulk.png
    :alt: Change page status in the record list
    :class: with-shadow

    Change page status in the record list


..  tip::
    Otherwise use the :ref:`console command <command>` to update multiple records at once.
