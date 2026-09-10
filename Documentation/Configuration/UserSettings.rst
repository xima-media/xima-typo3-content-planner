..  include:: /Includes.rst.txt

..  _user-settings:

=======================
User Settings
=======================

The following options can be set in the user settings:

..  _user-settings-hideContentPlannerStatus:

..  confval:: hideContentPlannerStatus
    :type: boolean
    :Default: 0

    If enabled, the Content Planner status information and colors will be hidden in the TYPO3 backend.

    ..  note::
        Useful if you have a color overload in the backend or if you don't use the Content Planner status feature.

..  _user-settings-repliesExpanded:

..  confval:: repliesExpanded
    :type: boolean
    :Default: 0

    If enabled, threaded replies in the comment modal are expanded by default instead of collapsed.

    This setting can also be toggled directly from the comment modal's action menu via the "Expand replies" / "Collapse replies" button.

..  _user-settings-contentPlannerDigest:

..  confval:: Receive content planner email digest
    :type: boolean
    :Default: 1

    Controls whether this backend user receives the periodic email digest sent by the
    :ref:`content-planner:notification:digest <content-planner-notification-digest>` command.
    Enabled by default; disable it to opt out entirely without unwatching any records.

..  figure:: /Images/user-settings.jpg
    :alt: Content Planner User Settings
