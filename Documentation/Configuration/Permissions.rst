..  include:: /Includes.rst.txt

..  _permissions:

=======================
Permissions
=======================

Access to the Content Planner functionalities is generally available to all admin users.

All other users require the necessary authorization via the TYPO3 backend permissions module.

Via the "Access Rights" tab add the **Content Status** (`tx_ximatypo3contentplanner:content-status`) permission within the *Custom module options*

..  note::
    Only users with admin rights and the necessary permissions can access the Content Planner functionalities and can be selected as assignees.

Don't forget to also add the following permissions as well:

- "Tables (listing)" (tables_select) and "Tables (modify)" (tables_modify) permissions for the `Content Planner Comment [tx_ximatypo3contentplanner_comment]` table
- All wanted :ref:`dashboard widget <dashboard-widgets>` in the "Dashboard widgets" (availableWidgets) permission
