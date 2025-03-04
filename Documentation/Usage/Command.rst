..  include:: /Includes.rst.txt

..  _command:

=======================
Command
=======================

The extension provides the following console commands:

..  _content-planner:bulk-update:

`content-planner:bulk-update`
=====================

A command to update multiple records based on a given configuration.

..  tabs::

    ..  group-tab:: Composer-based installation

        ..  code-block:: bash

            vendor/bin/typo3 content-planner:bulk-update

    ..  group-tab:: Legacy installation

        ..  code-block:: bash

            typo3/sysext/core/bin/typo3 content-planner:bulk-update

The following command arguments are available:

..  confval:: table
    :Required: false
    :type: string
    :Default: "pages"
    :Multiple allowed: false

    Defines the table of content planner records to be updated.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 content-planner:bulk-update pages
                vendor/bin/typo3 content-planner:bulk-update sys_category

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 content-planner:bulk-update pages
                typo3/sysext/core/bin/typo3 content-planner:bulk-update sys_category

..  confval:: uid
    :Required: false
    :type: integer
    :Default: 1
    :Multiple allowed: false

    Defines the uid of the record to be updated.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 content-planner:bulk-update pages 12
                vendor/bin/typo3 content-planner:bulk-update sys_category 32

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 content-planner:bulk-update pages 12
                typo3/sysext/core/bin/typo3 content-planner:bulk-update sys_category 32

..  confval:: status
    :Required: false
    :type: integer
    :Default: none
    :Multiple allowed: false

    Defines the status uid to set. If empty, the status of the desired record will be cleared.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 content-planner:bulk-update pages 12 1
                vendor/bin/typo3 content-planner:bulk-update sys_category 32 2

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 content-planner:bulk-update pages 12 1
                typo3/sysext/core/bin/typo3 content-planner:bulk-update sys_category 32 2

The following command options are available:

..  confval:: -r|--recursive
    :Required: false
    :type: boolean
    :Default: false
    :Multiple allowed: false

    Use this option to update all records beginning from the defined record in the console arguments recursively.

    ..  note::
        This option is only available for the `pages` table.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 content-planner:bulk-update pages 1 1 -r

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 content-planner:bulk-update pages 1 1 -r

..  confval:: -a|--assignee
    :Required: false
    :type: integer
    :Default: none
    :Multiple allowed: false

    Use this option to assign the updated records to a specific user.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 content-planner:bulk-update pages 1 1 -a 2
                vendor/bin/typo3 content-planner:bulk-update pages 1 1 --assignee=2

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 content-planner:bulk-update pages 1 1 -a 2
                typo3/sysext/core/bin/typo3 content-planner:bulk-update pages 1 1 --assignee=2
