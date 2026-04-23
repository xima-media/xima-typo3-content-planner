..  include:: /Includes.rst.txt

..  _comments:

=======================
Comments
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

Every backend user with Content Planner access can add comments to records. Comments are a great way to communicate with other users about the status of a record or to provide additional information.

..  figure:: /Images/comments.gif
    :alt: Screencast of comments
    :class: with-shadow

    Comments screencast

..  _comments-new:

Create new comment
===================

..  figure:: /Images/comment-new.png
    :alt: Create new comment modal dialog
    :class: with-shadow

    Create new comment modal dialog

..  _comments-show:

Show comments
=============

..  figure:: /Images/comment-hint.png
    :alt: Hint for comments
    :class: with-shadow

    Hint for comments

..  figure:: /Images/comment-show.jpg
    :alt: Show comments of a record
    :class: with-shadow

    Show comments of a record

..  _comments-edit:

Edit comments
=============

..  versionadded:: 1.4.0

        `Feature: #70 - Add edit comment functionality and associated UI updates <https://github.com/xima-media/xima-typo3-content-planner/pull/70>`__

Use the context menu to edit, :ref:`resolve <comments-resolve>` or delete a comment.

..  figure:: /Images/comment-edit.jpg
    :alt: Edit or delete comments of a record

    Edit, resolved or delete comments of a record

An edited comment is marked with a label.

..  figure:: /Images/comment-edited.jpg
    :alt: Edited comment

    Subsequently edited comment

..  _comments-todo:

ToDos
=============

..  versionadded:: 1.4.0

        `Feature: #69 - Add todo tracking to comments with resolved and total counts <https://github.com/xima-media/xima-typo3-content-planner/pull/69>`__

Use the ToDo list within the editor to track the progress of your comments.

..  figure:: /Images/todo-editor.jpg
    :alt: ToDo list in editor

    ToDo list in editor

The ToDo count is automatically updated when you add or remove a ToDo item in the comment.

..  figure:: /Images/todo.jpg
    :alt: ToDo count in header

    ToDo count in header

Use the :ref:`ToDo widget <dashboard-widgets>` to keep track of your ToDo tasks.

..  figure:: /Images/widget-todo.jpg
    :alt: ToDo widget

    ToDo widget

..  _comments-resolve:

Resolution
=================

..  versionadded:: 1.4.0

        `Feature: #72 - Add comment resolution functionality with sorting and filtering options <https://github.com/xima-media/xima-typo3-content-planner/pull/72>`__

In addition to the ToDos in the comments, there is also the option of marking entire comments as completed, in order to keep the comment list clear and organized.

A comment can be marked as completed via the context menu.

..  figure:: /Images/comment-resolve.jpg
    :alt: Resolved comments

    Resolved comments can can be displayed again using the filter

..  _comments-replies:

Threaded Replies
=================

..  versionadded:: 2.2.0

Comments support single-level threaded replies. Each root comment can have replies that are displayed as an indented, collapsible section below it.

Creating replies
-----------------

Use the **Reply** action in the comment's dropdown menu or click the reply button at the bottom of the reply list. A reply is created the same way as a regular comment — through the FormEngine modal.

..  figure:: /Images/comment-reply-menu.jpg
    :alt: Reply action in dropdown menu
    :class: with-shadow

    Reply action in the comment dropdown menu

Replies to replies are automatically flattened to a single level (all replies belong to the root comment).

Collapsible section
--------------------

Replies are collapsed by default. A toggle shows the reply count and the time of the most recent reply (e.g., "3 replies · last 5 minutes ago"). Click the toggle to expand or collapse the reply list.

..  figure:: /Images/comment-reply.jpg
    :alt: Threaded reply with collapsible section
    :class: with-shadow

    Expanded reply section with inline reply button

Sorting
--------

Root comments are sorted by **last activity** — either their own creation date or the creation date of their most recent reply, whichever is newer. This means a root comment with a fresh reply automatically moves to the top. The "Newest/Oldest" dropdown controls the sort direction for both root comments and replies.

Comment count
--------------

The comment count badge in the page header includes both root comments and replies. This reflects the total discussion activity on a record.

Activity stream & widgets
--------------------------

Replies appear in the activity stream with the label "A new reply comment has been added" and in the comment dashboard widget with a reply badge.

Share links for replies automatically expand the collapse section and scroll to the specific reply.

..  _comments-share:

Share link
=================

..  versionadded:: 2.1.0

Comments and the entire comment modal can be shared with other backend users via a direct link. The link navigates the recipient to the record, automatically opens the comment modal and, if a specific comment was shared, scrolls to it and briefly highlights it.

Use the context menu of a comment to copy the share link to the clipboard. To share a link to the entire comment modal, use the action menu in the filter bar.

..  figure:: /Images/comment-share.jpg
    :alt: Share link for comments

    Share a direct link to a comment or the comment modal
