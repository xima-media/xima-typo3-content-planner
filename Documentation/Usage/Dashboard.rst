..  include:: /Includes.rst.txt

..  _dashboard:

=======================
Dashboard
=======================

The extension brings a prepared dashboard preset with a set of widgets to support the content planning process.

Create
=====================

To create a new dashboard, click on the "Add dashboard" (+) button near the dashboard tabs in the dashboard module.

..  figure:: /Images/dashboard-new.png
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


.. t3-field-list-table::
    :header-rows: 1

    -
        :Field:
            Widget:

        :Description:
            Description:

    -
        :Field:
            Overview

        :Description:
            Bar chart showing the distribution of records by status.

    -
        :Field:
            Recent Updates

        :Description:
            Update stream showing the latest changes to the content planner records.

    -
        :Field:
            Current Assignee

        :Description:
            Short list with all records assigned to the current user.

    -
        :Field:
            ToDo

        :Description:
            Display all records with open todos in the comments.

    -
        :Field:
            Recent Comments

        :Description:
            Showings the latest comments on the content planner records.

    -
        :Field:
            Status

        :Description:
            Filterable list of all records with the current status.

    -
        :Field:
            Content Planner (Configurable)

        :Description:
            A fully customizable widget that combines the functionality of multiple widgets.
            Available in TYPO3 v14+ only. See :ref:`dashboard-configurable-widget` for details.

..  _dashboard-configurable-widget:

Configurable Widget
===================

..  important::

    This widget requires TYPO3 v14+ and uses the new `Dashboard Widget Settings API <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Feature-107036-ConfigurableDashboardWidgets.html>`__.

The **Content Planner (Configurable)** widget is a powerful, customizable widget.
It allows you to create multiple instances with different configurations, each tailored to your specific needs.

Settings
--------

The widget can be configured through the widget settings dialog:

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
