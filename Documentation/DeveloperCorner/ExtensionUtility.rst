..  include:: /Includes.rst.txt

..  _extension_utility:

=======================
Extension Utility
=======================

The :php:`ExtensionUtility` is the public API for TCA configuration and feature
management. Use it to register additional record tables (see
:ref:`additional-records`) and to query the current extension configuration
from your own code.

..  php:namespace:: Xima\XimaTypo3ContentPlanner\Utility

..  php:class:: ExtensionUtility

    Utility class for TCA configuration and feature management.

    ..  php:method:: addContentPlannerTabToTCA($table)

        Add the "Content Planner" tab (status, assignee and comments palette) to
        the TCA of the given table. Call this in a
        :file:`Configuration/TCA/Overrides/<table>.php` file.

        :param string $table: Table name to extend.
        :returntype: :php:`void`

    ..  php:method:: getRecordTables()

        Get all tables that are tracked by the content planner. Includes
        :sql:`pages`, the optionally enabled :sql:`tt_content` and filelist
        tables as well as all tables registered via
        :php:`registerAdditionalRecordTables`.

        :returntype: :php:`string[]`

    ..  php:method:: isRegisteredRecordTable($table)

        Check whether the given table is tracked by the content planner.

        :param string $table: Table name to check.
        :returntype: :php:`bool`

    ..  php:method:: isFilelistSupportEnabled()

        Check whether filelist support (files and folders) is enabled via the
        :ref:`enableFilelistSupport <extconf-enableFilelistSupport>` extension
        configuration.

        :returntype: :php:`bool`

    ..  php:method:: isContentElementSupportEnabled()

        Check whether content element support (:sql:`tt_content`) is enabled via
        the :ref:`enableContentElementSupport <extconf-enableContentElementSupport>`
        extension configuration.

        :returntype: :php:`bool`

    ..  php:method:: isFeatureEnabled($feature)

        Check whether a boolean extension configuration feature is enabled.

        :param string $feature: Configuration key, e.g. ``commentTodos``.
        :returntype: :php:`bool`

    ..  php:method:: getExtensionSetting($feature)

        Get the raw value of an extension configuration option as string.

        :param string $feature: Configuration key, e.g. ``treeStatusInformation``.
        :returntype: :php:`string`

..  seealso::

    View the sources on GitHub:

    -   `ExtensionUtility <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Utility/ExtensionUtility.php>`__
