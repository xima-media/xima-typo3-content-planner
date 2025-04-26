..  include:: /Includes.rst.txt

..  _comments:

=======================
Comments
=======================

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
