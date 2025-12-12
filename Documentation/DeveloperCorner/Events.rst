..  include:: /Includes.rst.txt

..  _events:

=======================
PSR-14 Events
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

The extensions contains some PSR-14 events which make it possible to extend the extension with own functionality.
You can for example adjust the status selection or react on status changes for implementing some kind of a workflow.

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
            $oldStatus = $event->getOldStatus();

            // Example: Send notification when status changes to "Needs Review"
            if ($newStatus?->getTitle() === 'Needs Review') {
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


..  seealso::

    View the sources on GitHub:

    -   `PrepareStatusSelectionEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/PrepareStatusSelectionEvent.php>`__
    -   `StatusChangeEvent <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Event/StatusChangeEvent.php>`__
