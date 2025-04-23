..  include:: /Includes.rst.txt

..  _planner_utility:

=======================
Planner Utility
=======================

The :php:`PlannerUtility` can be used to easily interact programmatically with the content planner.

..  php:namespace:: Xima\XimaTypo3ContentPlanner\Utility

..  php:class:: PlannerUtility

    Utility class to use content planner functionalities.

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

    ..  php:method:: getCommentsOfRecord($table, $uid, $raw = false)

        Simple function to fetch all comments of a record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param bool $raw: Get raw comment records instead of optimized DTOs.
        :returntype: :php:`array`

    ..  php:method:: addCommentsToRecord($table, $uid, $comments, $author = null)

        Simple function to fetch all comments of a record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param array|string $comments: Single comment string or array of multiple comments in a row.
        :param \Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser|int|string|null $author: Optional user object, UID or username of the author.
        :returntype: :php:`void`

    ..  php:method:: generateTodoForComment($todos)

        Simple function to generate the html todo markup for a comment to easily insert them into the comment content.

        :param array $todos: Array of todo strings.
        :returntype: :php:`string`

    ..  php:method:: clearCommentsOfRecord($table, $uid, $like = null)

        Simple function to clear all comment(s) of a content planner record.

        :param string $table: Table name of the record.
        :param int $uid: UID of the record.
        :param string|null $like: Optional string to filter comments by content.
        :returntype: :php:`void`

..  seealso::

    View the sources on GitHub:

    -   `PlannerUtility <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Utility/PlannerUtility.php>`__
