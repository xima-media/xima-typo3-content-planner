..  include:: /Includes.rst.txt

..  _dashboard:

=======================
Dashboard
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

The extension brings a prepared dashboard preset with a set of widgets to support the content planning process.

Create
=====================

To create a new dashboard, click on the "Add dashboard" (+) button near the dashboard tabs in the dashboard module.

..  figure:: /Images/dashboard-new.jpg
    :alt: Create new dashboard using the preset
    :class: with-shadow

    Create new dashboard using the preset

Otherwise you can add the widgets manually to an existing dashboard.

..  figure:: /Images/widget-select.jpg
    :alt: Select the content planner widgets
    :class: with-shadow

    Select the content planner widgets

..  _dashboard-widgets:

Widgets
=====================

The dashboard provides a set of helpful widgets to get an overview of the current status of the content planner records.

..  figure:: /Images/dashboard-overview.png
    :alt: Dashboard overview
    :class: with-shadow

    Dashboard overview


..  image:: /Images/widgets/dashboard-overview.png
    :width: 64
    :alt: Overview

Overview
    Bar chart showing the distribution of records by status.

..  image:: /Images/widgets/dashboard-updates.png
    :width: 64
    :alt: Recent Updates

Recent Updates
    Update stream showing the latest changes to the content planner records.

..  image:: /Images/widgets/dashboard-assignee.png
    :width: 64
    :alt: Key figures

Key figures
    Three figures side by side: records assigned to you, records with open to-dos and records with open comments.
    The counts are the ones of the quick filters in the records module, so a figure always equals the list behind it.
    Each figure carries a "Show" link to the records module with the matching filter applied. A figure of zero has no link.
    The widget spans the whole width of the dashboard.

..  image:: /Images/widgets/dashboard-comments.png
    :width: 64
    :alt: Recent Comments

Recent Comments
    Shows the latest comments on the content planner records. Links in a comment are followable, a link to a page opens
    that page in the page module.

..  image:: /Images/widgets/dashboard-status.png
    :width: 64
    :alt: Status

Status
    The latest records with the current status, the assignee, the time of the last change and an activity column.
    The activity column shows the number of comments and the to-do progress, each on its own line.
    The widget has no search or filter of its own: "Show all records" leads to the records module, which does that.
    For a fixed view, for example a review queue, use the configurable widget.

..  image:: /Images/widgets/dashboard-custom.png
    :width: 64
    :alt: Content Planner (Configurable)

Content Planner (Configurable)
    A fully customizable widget that combines the functionality of multiple widgets.
    Available in TYPO3 v14+ only. See :ref:`dashboard-configurable-widget` for details.

..  _dashboard-configurable-widget:

Configurable Widget
===================

..  versionadded:: 2.0.0

        `Feature: #161 - Add configurable dashboard widget <https://github.com/xima-media/xima-typo3-content-planner/pull/161>`__

..  important::

    This widget requires TYPO3 v14+ and uses the new `Dashboard Widget Settings API <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Feature-107036-ConfigurableDashboardWidgets.html>`__.

The **Content Planner (Configurable)** widget is a powerful, customizable widget.
It allows you to create multiple instances with different configurations, each tailored to your specific needs.

Settings
--------

The widget can be configured through the widget settings dialog:

..  figure:: /Images/widget-custom.jpg
    :alt: Configurable widget settings dialog
    :class: with-shadow

    Widget settings dialog with all available filter options

.. t3-field-list-table::
    :header-rows: 1

    -
        :Setting:
            Setting:

        :Description:
            Description:

    -
        :Setting:
            Custom Title

        :Description:
            Set a custom title for the widget. If left empty, an automatic title based on the selected mode will be used.

    -
        :Setting:
            Display Mode

        :Description:
            Choose what kind of records to display:

            - **All Status Records**: Shows all records with any content planner status
            - **Assigned Records**: Shows records filtered by assignee
            - **Records with open TODOs**: Shows only records that have open tasks in their comments

    -
        :Setting:
            Status Filter

        :Description:
            Filter records by a specific status. Select "All statuses" to show records with any status.

    -
        :Setting:
            Assignee Filter

        :Description:
            Filter records by assignee:

            - **All assignees**: No assignee filter
            - **Current User**: Shows only records assigned to the logged-in user
            - **Specific user**: Select a specific backend user

    -
        :Setting:
            Record Type Filter

        :Description:
            Filter records by table type (e.g., pages, news, etc.). Select "All record types" to show all registered tables.

Use Cases
---------

Here are some example configurations:

**Personal Task List**
    Set "Display Mode" to "Assigned Records" and "Assignee Filter" to "Current User" with a custom title like "My Tasks".

**Review Queue**
    Set "Status Filter" to your "In Review" status to create a dedicated review queue widget.

**Open TODOs Overview**
    Set "Display Mode" to "Records with open TODOs" to track all unfinished tasks across the project.

**News Articles Status**
    Set "Record Type Filter" to "News" to monitor only news article statuses.
