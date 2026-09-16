..  include:: /Includes.rst.txt

..  _upgrade-guide:

=============
Upgrade guide
=============

..  contents:: Table of Contents
    :local:
    :depth: 2

This page documents breaking changes for extension authors who consume this
extension's public PHP API (events, repositories, models).

3.0.0
=====

``Status`` is now a readonly DTO
---------------------------------

``Xima\XimaTypo3ContentPlanner\Domain\Model\Status`` no longer extends
Extbase's ``AbstractEntity``. It is now a plain, ``final readonly`` value
object hydrated directly from the database by
``Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository``.

This affects the payload of two PSR-14 events:

-   ``StatusChangeEvent::getPreviousStatus()`` / ``getNewStatus()``
-   ``PrepareStatusSelectionEvent::getCurrentStatus()``

All of ``Status``'s existing getters (``getUid()``, ``getTitle()``,
``getIcon()``, ``getColor()``, ``getColoredIcon()``) are unchanged, so most
event listeners that only read these values require no changes.

What changed in practice:

-   ``Status`` can no longer be constructed via Extbase's zero-argument
    constructor followed by setters (``setTitle()``, ``setIcon()``,
    ``setColor()``) - it is now constructed with named, readonly constructor
    arguments: ``new Status(uid: 1, title: 'Draft', icon: 'flag', color: 'blue')``.
-   ``Status`` can no longer be persisted, queried, or otherwise touched via
    Extbase's ``QueryInterface``/``Repository`` API.
-   Because ``Status`` is ``final``, it can no longer be mocked with
    ``$this->createMock(Status::class)`` in tests - construct a real
    instance instead.

If your listener only reads status data via the getters above, no changes
are required.
