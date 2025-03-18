..  include:: /Includes.rst.txt

..  _events:

=======================
PSR-14 Events
=======================

The extensions contains some PSR-14 events which make it possible to extend the extension with own functionality.
You can for example adjust the status selection or react on status changes for implementing some kind of a workflow.

Please note, that there is no documentation for each PSR-14 event in detail, so you have to check each event
individually for supported properties. Generally I tried to make the events as self explaining as possible.

If you are new to PSR-14 events, please refer to the official TYPO3 documentation about
PSR-14 events and Event Listeners.

https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/Hooks/EventDispatcher/Index.html

The following PSR-14 events are available:

* :php:`PrepareStatusSelectionEvent`
* :php:`StatusChangeEvent`


..  seealso::

    View the sources on GitHub:

    -   https://github.com/xima-media/xima-typo3-content-planner/tree/main/Classes/Event
