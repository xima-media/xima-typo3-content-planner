..  include:: /Includes.rst.txt

..  _extension-configuration:

=======================
Extension configuration
=======================

#. Go to :guilabel:`Admin Tools > Settings > Extension Configuration`
#. Choose :guilabel:`xima_typo3_content_planner`

The extension currently provides the following configuration options:

Features
=======

..  _extconf-recordListStatusInfo:

..  confval:: recordListStatusInfo
    :type: boolean
    :Default: 1

    Enable record list status info

..  _extconf-enableFilelistSupport:

..  confval:: enableFilelistSupport
    :type: boolean
    :Default: 1

    Enable filelist support for files (sys_file_metadata) and folders

..  _extconf-enableContentElementSupport:

..  confval:: enableContentElementSupport
    :type: boolean
    :Default: 1

    Enable content element support (tt_content). When enabled, content elements can have their own status, assignee and comments.

..  _extconf-recordEditHeaderInfo:

..  confval:: recordEditHeaderInfo
    :type: boolean
    :Default: 1

    Enable record edit header info

..  _extconf-webListHeaderInfo:

..  confval:: webListHeaderInfo
    :type: boolean
    :Default: 1

    Enable web list header info

..  _extconf-treeStatusInformation:

..  confval:: treeStatusInformation
    :type: options
    :Default: comments

    Enable additional information in the page tree. Choose between:

    - **Nothing** (0): No additional information
    - **Comments** (comments): Show comment count
    - **Todos** (todos): Show todo count

..  _extconf-resetContentElementStatusOnPageReset:

..  confval:: resetContentElementStatusOnPageReset
    :type: boolean
    :Default: 0

    Reset status of content element, if status on corresponding page is reset

    ..  note::
        Requires :ref:`enableContentElementSupport <extconf-enableContentElementSupport>` to be enabled (default).

Assignee
=======

..  _extconf-autoAssignment:

..  confval:: autoAssignment
    :type: boolean
    :Default: 1

    Enable the auto assignment of the current user when a new status is set to the record.

..  _extconf-currentAssigneeHighlight:

..  confval:: currentAssigneeHighlight
    :type: boolean
    :Default: 1

    Enable the current assignee hint to highlight records assigned to the current user.

    Records with your user assigned will be highlighted for you in a light yellow color.

Comments
=======

..  _extconf-clearCommentsOnStatusReset:

..  confval:: clearCommentsOnStatusReset
    :type: boolean
    :Default: 1

    Delete corresponding comments when status is reset

..  _extconf-commentTodos:

..  confval:: commentTodos
    :type: boolean
    :Default: 1

    Parse the :ref:`todos <comments-todo>` from comments and show them as separate hint

    ..  figure:: /Images/todo.jpg
        :alt: Todos from comments

        Todos from comments
