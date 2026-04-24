..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

This TYPO3 extension adds content planning capabilities to the TYPO3 backend:
assign a **status** to pages (and other records), **assign** responsible editors
and leave **comments** with todos — all directly in the page tree, record list and file list.

Ideal for content migrations, editorial workflows or any process where you need to track
who is working on what.

..  figure:: /Images/page.jpg
    :alt: Content Planner Intro

..  _features:

Features
========

:ref:`Status <status>`
    Colorful, customizable status labels for pages and records.
    :ref:`Change status <status-change>` via page tree, module header, record list or edit form.
    Bulk update multiple records at once or via :ref:`console command <command>`.

:ref:`Assignee <assignee>`
    Assign responsible editors to records.
    Quick actions: "Assign to me" / "Unassign" shortcuts.
    Optional auto-assignment on status change.

:ref:`Comments <comments>`
    Discuss records directly in the backend.
    :ref:`Todos <comments-todo>` and :ref:`resolution <comments-resolve>` tracking within comments.
    :ref:`Threaded replies <comments-replies>` and :ref:`shareable links <comments-share>`.

:ref:`Dashboard <dashboard>`
    Ready-to-use dashboard preset with dedicated :ref:`widgets <dashboard-widgets>`.
    Overview, status, updates, assignee, todo, comments widgets.
    :ref:`Configurable widget <dashboard-configurable-widget>` with custom filters (TYPO3 v14+).

Extensibility
    Extend :ref:`additional database records <additional-records>` with status behavior.
    Built-in support for content elements and :ref:`files/folders <filelist>`.

Screencast
----------

..  figure:: /Images/screencast.gif
    :alt: Screencast Content Planner

..  _support:

Support
=======

There are several ways to get support for this extension:

* GitHub: https://github.com/xima-media/xima-typo3-content-planner/issues

License
=======

This extension is licensed under
`GNU General Public License 2.0 (or later) <https://www.gnu.org/licenses/old-licenses/gpl-2.0.html>`_.
