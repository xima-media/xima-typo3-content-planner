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

..  _user-settings-includeChildComments:

..  confval:: includeChildComments
    :type: boolean
    :Default: 0

    If enabled, a page's comment view additionally shows comments left on child records living on
    that page (content elements, or any other registered record type, e.g. a news record inside a
    sysfolder), grouped by record with a deep link to each one.

    ..  note::
        This only changes what the comment view *displays*. Page tree badges and comment counts
        still count a page's own comments only.

    This setting can also be toggled directly from the checkbox above a page's comment list.

..  figure:: /Images/user-settings.jpg
    :alt: Content Planner User Settings
