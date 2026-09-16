..  include:: /Includes.rst.txt

..  _planner_service:

=======================
Planner Service
=======================

:php:`PlannerService` is the injectable service backing :ref:`PlannerUtility <planner_utility>`.
Inject it via the constructor wherever dependency injection is available - it is easier to
test and makes the dependency on the content planner explicit.

..  php:namespace:: Xima\XimaTypo3ContentPlanner\Service

..  php:class:: PlannerService

    Injectable service to interact programmatically with the content planner.

    ..  php:method:: getListOfStatus()

        Simple function to get a list of all available status.

        :returntype: :php:`array`

    ..  php:method:: updateStatusForRecord($table, $uid, $status, $assignee = null)

        Simple function to update the status of a record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|int|string $status: Status object, UID or title of the status.
        :param \Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser|int|string|null $assignee: Optional user object, UID or username of the assignee.
        :returntype: :php:`void`

    ..  php:method:: getStatusOfRecord($table, $uid)

        Simple function to get the status of a record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :returntype: :php:`\Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null`

    ..  php:method:: getStatus($identifier)

        Simple function to get a status.

        :param int|string $identifier: UID or title of the status record.
        :returntype: :php:`\Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null`

    ..  php:method:: getCommentsOfRecord($table, $uid, $raw = false, $showResolved = false)

        Simple function to fetch all comments of a record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param bool $raw: Get raw comment records instead of optimized DTOs.
        :param bool $showResolved: Include resolved comments and replies. By default they are omitted.
        :returntype: :php:`array`

    ..  php:method:: addCommentsToRecord($table, $uid, $comments, $author = null, $parentUid = 0)

        Simple function to add comment(s) to a content planner record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param array|string $comments: Single comment string or array of multiple comments in a row.
        :param \Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser|int|string|null $author: Optional user object, UID or username of the author.
        :param int $parentUid: UID of the parent comment to reply to. Must belong to the same record, otherwise an :php:`\InvalidArgumentException` is thrown. ``0`` creates a top-level comment. If it identifies an existing reply rather than a root comment, the new comment is attached to that reply's root comment instead.
        :returntype: :php:`void`

    ..  php:method:: clearCommentsOfRecord($table, $uid, $like = null)

        Simple function to clear all comment(s) of a content planner record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param string|null $like: Optional string to filter comments by content.
        :returntype: :php:`void`

..  seealso::

    View the sources on GitHub:

    -   `PlannerService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/PlannerService.php>`__
