..  include:: /Includes.rst.txt

..  _permissions:

=======================
Permissions
=======================

Access to the Content Planner functionalities is generally available to all admin users.

All other users require the necessary authorization via the TYPO3 backend permissions module.

Basic Permissions
=================

Via the "Access Rights" tab add one of the following permissions within the *Custom module options*:

**View Only** (`tx_ximatypo3contentplanner:view-only`)
    Enables Content Planner visibility (status indicators, comments panel) without any action permissions. Use this in combination with granular permissions.

**Full Access** (`tx_ximatypo3contentplanner:content-status`)
    Grants visibility and all Content Planner permissions at once. This is useful for power users who should have unrestricted access to all features.

..  note::
    Only users with admin rights or the necessary permissions can access the Content Planner functionalities and can be selected as assignees.

Granular Permissions
====================

In addition to the basic permissions, you can configure granular permissions to control specific actions. These require either **View Only** or **Full Access** as a prerequisite for visibility.

Status Permissions
------------------

**Change Status** (`tx_ximatypo3contentplanner:status-change`)
    Allow changing the status of records. Without this permission, users can view status information but cannot modify it.

**Unset Status** (`tx_ximatypo3contentplanner:status-unset`)
    Allow removing/resetting the status from records. Users without this permission can only set a status, but not clear it.

Comment Permissions
-------------------

..  note::
    Comment permissions require that users also have **Tables (modify)** (`tables_modify`) permission for the `Content Planner Comment [tx_ximatypo3contentplanner_comment]` table in TYPO3's standard access rights.

**Create Comments** (`tx_ximatypo3contentplanner:comment-create`)
    Allow creating new comments on records.

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

..  note::
    Users need at least one assignment permission to edit the assignee field. Without any assignment permission, the assignee field is displayed as read-only.

**Assign Self** (`tx_ximatypo3contentplanner:assign-self`)
    Allow assigning and unassigning yourself. Users with this permission can use the "Assign to me" shortcut and select themselves from the assignee dropdown. Unassigning is only possible if the record is currently assigned to the user themselves.

**Assign Others** (`tx_ximatypo3contentplanner:assign-others`)
    Allow assigning, reassigning and unassigning any user. This is a superset of *Assign Self* - users with this permission can select any user from the assignee dropdown, change existing assignments, and unassign anyone.

Read-Only Fields in Record Editing
===================================

When editing records (e.g. page properties), the Content Planner fields are automatically set to **read-only** if the user lacks the corresponding permission:

- **Status field**: read-only without *Change Status* permission
- **Assignee field**: read-only without any assignment permission (*Assign Self* or *Assign Others*)
- **Comments field**: read-only without *Create Comments* permission (or missing `tables_modify` for the comment table)

This ensures that users with *View Only* access can see the current status, assignee, and comments, but cannot modify them directly in the record form.

Per-Group Restrictions
======================

In addition to the custom module options, you can restrict which statuses and tables a user group can work with:

Allowed Statuses
----------------

In the backend user group settings (Content Planner tab), you can specify which statuses are allowed for the group. If left empty, all statuses are available.

Allowed Tables
--------------

Similarly, you can restrict which record tables (pages, tt_content, sys_file_metadata, etc.) a group can manage with Content Planner features.

Migration from Previous Versions
================================

If you upgrade from a version without granular permissions, existing user groups with the **Content Status** permission will continue to work as before - the permission now grants full access.

To use granular permissions:

1. Use **View Only** for read-only access or **Full Access** for unrestricted access
2. Add the desired fine-grained permissions for each user group
3. Optionally restrict allowed statuses and tables per group

..  tip::
    If you want to keep the old behavior (full access for all), simply use the **Full Access** permission. It grants all Content Planner features at once.

Additional Required Permissions
===============================

Don't forget to also add the following permissions as well:

- "Tables (listing)" (`tables_select`) and "Tables (modify)" (`tables_modify`) permissions for the `Content Planner Comment [tx_ximatypo3contentplanner_comment]` table
- All wanted :ref:`dashboard widget <dashboard-widgets>` in the "Dashboard widgets" (`availableWidgets`) permission
