..  include:: /Includes.rst.txt

..  _permissions:

=======================
Permissions
=======================

Access to the Content Planner functionalities is generally available to all admin users.

All other users require the necessary authorization via the TYPO3 backend permissions module.

Basic Permissions
=================

Via the "Access Rights" tab add the **Content Status** (`tx_ximatypo3contentplanner:content-status`) permission within the *Custom module options*.

..  note::
    Only users with admin rights and the necessary permissions can access the Content Planner functionalities and can be selected as assignees.

Granular Permissions
====================

In addition to the basic **Content Status** permission, you can configure granular permissions to control specific actions:

Full Access
-----------

**Full Access** (`tx_ximatypo3contentplanner:full-access`)
    Grants all Content Planner permissions at once. This is useful for power users who should have unrestricted access to all features.

Status Permissions
------------------

**Change Status** (`tx_ximatypo3contentplanner:status-change`)
    Allow changing the status of records. Without this permission, users can view status information but cannot modify it.

**Unset Status** (`tx_ximatypo3contentplanner:status-unset`)
    Allow removing/resetting the status from records. Users without this permission can only set a status, but not clear it.

Comment Permissions
-------------------

**Edit Own Comments** (`tx_ximatypo3contentplanner:comment-edit-own`)
    Allow editing of comments created by the user themselves.

**Edit Foreign Comments** (`tx_ximatypo3contentplanner:comment-edit-foreign`)
    Allow editing of comments created by other users.

**Resolve Comments** (`tx_ximatypo3contentplanner:comment-resolve`)
    Allow marking comments as resolved or unresolving them.

**Delete Own Comments** (`tx_ximatypo3contentplanner:comment-delete-own`)
    Allow deletion of comments created by the user themselves.

**Delete Foreign Comments** (`tx_ximatypo3contentplanner:comment-delete-foreign`)
    Allow deletion of comments created by other users.

Assignment Permissions
----------------------

**Reassign** (`tx_ximatypo3contentplanner:assign-reassign`)
    Allow changing existing assignees.

**Assign Other Users** (`tx_ximatypo3contentplanner:assign-other-user`)
    Allow assigning other users, not just themselves.

Per-Group Restrictions
======================

In addition to the custom module options, you can restrict which statuses and tables a user group can work with:

Allowed Statuses
----------------

In the backend user group settings (Content Planner tab), you can specify which statuses are allowed for the group. If left empty, all statuses are available.

Allowed Tables
--------------

Similarly, you can restrict which record tables (pages, tt_content, sys_file_metadata, etc.) a group can manage with Content Planner features.

Example Configurations
======================

Editor (Limited)
----------------

- Content Status: Yes
- Change Status: Yes
- Edit Own Comments: Yes
- Delete Own Comments: Yes
- Allowed Statuses: Draft, In Review

Chief Editor
------------

- Content Status: Yes
- Full Access: Yes
- Allowed Statuses: All (leave empty)
- Allowed Tables: All (leave empty)

Content Manager
---------------

- Content Status: Yes
- Change Status: Yes
- Unset Status: Yes
- Edit Own/Foreign Comments: Yes
- Delete Own/Foreign Comments: Yes
- Resolve Comments: Yes
- Reassign: Yes
- Assign Other Users: Yes

Migration from Previous Versions
================================

If you upgrade from a version without granular permissions, existing user groups with the **Content Status** permission will continue to work as before - they automatically get full access (legacy mode).

**How Legacy Mode Works:**

- If a user has **only** the ``content-status`` permission (no granular permissions), they get **full access** to all features (same as before the update)
- As soon as you add **any** granular permission to a user group, that group switches to **granular mode** and only the explicitly granted permissions apply

To use granular permissions:

1. Keep the **Content Status** permission as the basic access gate
2. Add the desired fine-grained permissions for each user group
3. Optionally restrict allowed statuses and tables per group
4. Consider using **Full Access** for administrator-level groups instead of individual permissions

..  tip::
    If you want to keep the old behavior (full access for all), simply don't add any granular permissions. Users with ``content-status`` will continue to have full access.

Additional Required Permissions
===============================

Don't forget to also add the following permissions as well:

- "Tables (listing)" (tables_select) and "Tables (modify)" (tables_modify) permissions for the `Content Planner Comment [tx_ximatypo3contentplanner_comment]` table
- All wanted :ref:`dashboard widget <dashboard-widgets>` in the "Dashboard widgets" (availableWidgets) permission
