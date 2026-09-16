..  include:: /Includes.rst.txt

..  _events:

=======================
PSR-14 Events
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

The extension contains some PSR-14 events which make it possible to extend the extension with own functionality.
You can for example adjust the status selection or react on status changes for implementing some kind of a workflow.

..  note::

    Since 3.0.0, the ``Status`` objects passed via ``StatusChangeEvent`` and
    ``PrepareStatusSelectionEvent`` are readonly value objects rather than
    Extbase entities. See the :ref:`upgrade-guide` for details.

If you are new to PSR-14 events, please refer to the official TYPO3 documentation about
`PSR-14 events and Event Listeners <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/EventDispatcher/Index.html>`__.

PrepareStatusSelectionEvent
===========================

This event is dispatched before the status selection is rendered. You can use it to modify the available status options.

..  code-block:: php
    :caption: Classes/EventListener/ModifyStatusSelectionListener.php

    <?php
    namespace MyVendor\MyExtension\EventListener;

    use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;

    final class ModifyStatusSelectionListener
    {
        public function __invoke(PrepareStatusSelectionEvent $event): void
        {
            $table = $event->getTable();
            $uid = $event->getUid();
            $selectionEntries = $event->getSelectionEntriesToAdd();

            // Remove a specific status from selection
            unset($selectionEntries['3']);

            $event->setSelectionEntriesToAdd($selectionEntries);
        }
    }

..  code-block:: yaml
    :caption: Configuration/Services.yaml

    MyVendor\MyExtension\EventListener\ModifyStatusSelectionListener:
      tags:
        - name: event.listener
          identifier: 'my-extension/modify-status-selection'

StatusChangeEvent
=================

This event is dispatched after the status of a record has been changed. You can use it to trigger additional actions like notifications or workflow transitions.

Note that ``$newStatus`` may be ``null`` when a status is cleared from a record.

..  note::

    Since 3.0.0, the event also carries the acting backend user's UID via ``getActorUid()``,
    which may be ``null`` when unavailable (e.g. a CLI context without an authenticated backend
    user). Listeners should read this instead of falling back to ``$GLOBALS['BE_USER']``
    themselves, which is not reliably set outside a regular backend request.

    Three code paths deliberately bypass the DataHandler and therefore never dispatch this event
    (or ``AssigneeChangedEvent``): ``RecordRepository::updateStatusByUid()`` (used by the
    ``BulkUpdateCommand`` CLI command and, via ``PlannerService::updateStatusForRecord()``, by
    third-party integrations) and ``StatusChangeManager::clearStatusOfExtensionRecords()`` (an
    administrative mass-clear cascade). Both can affect many records per call, often from CLI
    where there is no reliable actor, so firing one event per row was deliberately left out to
    avoid an event storm. See the docblocks on those methods for the full reasoning.

    Since 3.1.0, this same bypass also keeps the :ref:`notification dispatcher <notifications>`
    silent for these three paths - it is purely event-driven, so no separate suppression was
    needed to make them notification-storm-safe. See :ref:`notifications` for the full
    reasoning and for the CLI-wide pause switch used elsewhere.

..  code-block:: php
    :caption: Classes/EventListener/StatusChangeListener.php

    <?php
    namespace MyVendor\MyExtension\EventListener;

    use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;

    final class StatusChangeListener
    {
        public function __invoke(StatusChangeEvent $event): void
        {
            $table = $event->getTable();
            $uid = $event->getUid();
            $newStatus = $event->getNewStatus();
            $previousStatus = $event->getPreviousStatus();

            // Example: Send notification when status changes to a specific status (uid 3)
            if ($newStatus?->getUid() === 3) {
                // Trigger notification logic
            }
        }
    }

..  code-block:: yaml
    :caption: Configuration/Services.yaml

    MyVendor\MyExtension\EventListener\StatusChangeListener:
      tags:
        - name: event.listener
          identifier: 'my-extension/status-change'


AssigneeChangedEvent
====================

..  note::

    Added in 3.0.0.

This event is dispatched whenever the assignee of a record actually changes, including as a side
effect of auto-assignment (the ``autoAssignment`` feature). It is dispatched from the same place
as ``StatusChangeEvent`` (``StatusChangeManager::processContentPlannerFields()``), so it shares
that method's raw-SQL-bypass caveat documented above.

Note that a request which never touches the status field at all is not processed by
``processContentPlannerFields()`` in the first place, so a pure assignee-only edit unrelated to
any status change still dispatches nothing - that pre-existing gap is unchanged by this event.

..  code-block:: php
    :caption: Classes/EventListener/AssigneeChangeListener.php

    <?php
    namespace MyVendor\MyExtension\EventListener;

    use Xima\XimaTypo3ContentPlanner\Event\AssigneeChangedEvent;

    final class AssigneeChangeListener
    {
        public function __invoke(AssigneeChangedEvent $event): void
        {
            $table = $event->getTable();
            $uid = $event->getUid();
            $previousAssignee = $event->getPreviousAssignee(); // be_users UID or null
            $newAssignee = $event->getNewAssignee();           // be_users UID or null
            $actorUid = $event->getActorUid();                 // acting backend user, or null (CLI)

            // Example: notify the newly assigned user
        }
    }

..  code-block:: yaml
    :caption: Configuration/Services.yaml

    MyVendor\MyExtension\EventListener\AssigneeChangeListener:
      tags:
        - name: event.listener
          identifier: 'my-extension/assignee-changed'

CommentCreatedEvent
====================

This event is dispatched after a new comment has been saved to the database. This includes both root comments and replies. Use it for notifications, activity logging, or integration with external systems.

The ``table`` property refers to the record being commented on (e.g. ``pages``), not the comment table itself.

..  code-block:: php
    :caption: Classes/EventListener/CommentNotificationListener.php

    <?php
    namespace MyVendor\MyExtension\EventListener;

    use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;

    final class CommentNotificationListener
    {
        public function __invoke(CommentCreatedEvent $event): void
        {
            $table = $event->getTable();           // e.g. 'pages'
            $recordUid = $event->getRecordUid();   // UID of the commented record
            $commentUid = $event->getCommentUid(); // UID of the new comment
            $authorUid = $event->getAuthorUid();   // UID of the backend user

            // Example: Send Slack notification
        }
    }

..  code-block:: yaml
    :caption: Configuration/Services.yaml

    MyVendor\MyExtension\EventListener\CommentNotificationListener:
      tags:
        - name: event.listener
          identifier: 'my-extension/comment-notification'

CommentResolvedEvent
=====================

This event is dispatched when a comment is marked as resolved. It is **not** dispatched when a comment is reopened (unresolved).

..  code-block:: php
    :caption: Classes/EventListener/CommentResolvedListener.php

    <?php
    namespace MyVendor\MyExtension\EventListener;

    use Xima\XimaTypo3ContentPlanner\Event\CommentResolvedEvent;

    final class CommentResolvedListener
    {
        public function __invoke(CommentResolvedEvent $event): void
        {
            $table = $event->getTable();               // e.g. 'pages'
            $recordUid = $event->getRecordUid();       // UID of the commented record
            $commentUid = $event->getCommentUid();     // UID of the resolved comment
            $resolvedByUid = $event->getResolvedByUid(); // UID of the resolving user

            // Example: Log resolution for audit trail
        }
    }

..  code-block:: yaml
    :caption: Configuration/Services.yaml

    MyVendor\MyExtension\EventListener\CommentResolvedListener:
      tags:
        - name: event.listener
          identifier: 'my-extension/comment-resolved'

..  seealso::

    View the sources on GitHub:

    -   `PrepareStatusSelectionEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/PrepareStatusSelectionEvent.php>`__
    -   `StatusChangeEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/StatusChangeEvent.php>`__
    -   `AssigneeChangedEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/AssigneeChangedEvent.php>`__
    -   `CommentCreatedEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/CommentCreatedEvent.php>`__
    -   `CommentResolvedEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/CommentResolvedEvent.php>`__
