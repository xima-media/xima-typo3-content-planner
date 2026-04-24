..  include:: /Includes.rst.txt

..  _status:

=======================
Status
=======================

Default Status
==================

By default, there are four different statuses available:

- **Pending**: The page is not yet ready for editing.
- **In progress**: The page is currently being edited.
- **Needs review**: The page is ready for review.
- **Completed**: The page is ready to be published.

..  figure:: /Images/status-init.png
    :alt: Initial status selection
    :class: with-shadow

..  note::
    These statuses are only intended as an initial configuration. Customize the statuses according to your needs.

Custom Status
==================

Statuses are managed as records on the root page (pid 0).

..  figure:: /Images/status-default.png
    :alt: Default Status Records
    :class: with-shadow

    Default Status Records

You can add a new status, edit an existing status, change the status order or delete a status.

..  figure:: /Images/status-edit.png
    :alt: Edit Status Record
    :class: with-shadow

    Edit Status Record


.. t3-field-list-table::
    :header-rows: 1

    -
        :Field:
            Field:

        :Description:
            Description:

    -
        :Field:
            Title

        :Description:
            Title of the status.

    -
        :Field:
            Icon

        :Description:
            Select one of the existing icons as representative picture of the status.

    -
        :Field:
            Color

        :Description:
            Select one of the existing colors for this status.
