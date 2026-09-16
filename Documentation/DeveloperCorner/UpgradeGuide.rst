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

``RecordRepository::findAllByFilter()`` now returns a ``PaginatedResult``
------------------------------------------------------------------------

``Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository::findAllByFilter()``
no longer returns a bare array of records. It returns
``Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult``, a small
readonly envelope with two public properties:

-   ``items``: the same list of records as before, already permission-filtered
    and truncated to ``$maxResults``.
-   ``hasMore``: ``true`` when more records the current backend user is
    allowed to see exist beyond the returned page.

This closes a bug where the SQL ``LIMIT`` was applied *before* permission
filtering, so a restricted backend user could see far fewer than
``$maxResults`` records (or none) even though matches existed further down the
result set. The fix over-fetches beyond the page size (see
``RecordRepository::FILTER_OVERFETCH_FACTOR``/``FILTER_OVERFETCH_CAP``) and
applies the permission check before truncating to the final page. If a whole
over-fetched window turns out to be invisible to the current user, the next
window is pulled rather than reporting a short page, up to
``FILTER_MAX_BATCHES`` windows. That bound keeps a pathological ratio of
invisible rows from turning one request into an unbounded scan; only beyond it
can ``hasMore`` still under-report.

Records are now ordered by ``tstamp DESC, tablename ASC, uid ASC`` instead of
``tstamp DESC`` alone. Paging by offset needs a total order, and records
sharing a timestamp previously came back in an order the database was free to
vary between queries.

If you call ``findAllByFilter()`` directly, replace:

..  code-block:: php

    $records = $recordRepository->findAllByFilter(...);
    foreach ($records as $record) { ... }

with:

..  code-block:: php

    $result = $recordRepository->findAllByFilter(...);
    foreach ($result->items as $record) { ... }
    // $result->hasMore tells you whether to show a "more results" indicator.

The JSON payload of the ``ximatypo3contentplanner_filterrecords`` AJAX route
(``RecordController::filterAction()``) changed accordingly, from a bare array
to ``{"items": [...], "hasMore": false}``.

Status/assignee/comment display now defaults to compact doc header chips
--------------------------------------------------------------------------

The extension configuration gained a new ``headerDisplayMode`` setting with
two values: ``chip`` (the new **default**) and ``banner`` (the previous
behaviour, up to and including 2.x).

-   In **chip** mode, the status dropdown, an assignee button and a comment
    button are shown as a compact trio in the record edit form's doc header
    button bar (via ``ModifyButtonBarEventListener``). Content elements in
    the page module get a 3px left accent border, a status dot and a mini
    comment badge instead of a full-surface overlay. The previous full-width
    banner above the module content, and the injected ``<style>`` overlay in
    the page module, are no longer rendered.
-   In **banner** mode, both ``RecordEditModifier`` (record edit form) and
    ``WebLayoutModifier`` (page module content elements) keep working exactly
    as before 3.0. Set ``headerDisplayMode = banner`` in the extension
    configuration to keep the pre-3.0 look, e.g. for migration projects that
    rely on the added prominence of the full-width banner.

This is a purely visual/configuration change; no PHP API (events,
repositories, models) is affected.
