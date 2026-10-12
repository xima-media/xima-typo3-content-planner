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
    ``WebLayoutModifier`` (page module content elements) keep the full-width
    banner of pre-3.0. Set ``headerDisplayMode = banner`` in the extension
    configuration to keep that layout, e.g. for migration projects that rely
    on the added prominence of the full-width banner. The banner itself was
    slimmed down in 3.0, see `Design refresh of the backend UI`_ below.

This is a purely visual/configuration change; no PHP API (events,
repositories, models) is affected.

Design refresh of the backend UI
--------------------------------

The backend UI follows a binding set of design rules in 3.0
(``docs/design/rules.md``). The following changes matter if you override
templates, listen to events or style the extension from your own assets.

**Colours.** Status tones are derived from the configured colour instead of
using fixed alpha values: ``_variables.css`` defines ``--cp-tint``,
``--cp-text``, ``--cp-edge`` and ``--cp-border`` for every element that carries
a ``content-planner-status`` class, and the ``--content-planner-color-*-light``
and ``--content-planner-color-highlight*`` custom properties are gone.
``Xima\XimaTypo3ContentPlanner\Configuration\Colors::get()`` lost its second
parameter (``$transparency``). Use ``Colors::getTint()`` for backgrounds and
``Colors::getEdge()`` for dots, flags and edges instead. The status icons use
the edge tone as well.

**Doc header group.** Status, assignee and comments are one button group now.
The assignee and comments buttons are ``GenericButton`` instances (they were
``LinkButton``) and carry ``data-*`` attributes as ordinary attributes, so
``getDataAttributes()`` no longer returns them. If you modify the button bar
through ``ModifyButtonBarEvent``, read the last group of the ``right`` position
instead of expecting three separate groups.

**Templates.** ``Backend/Header/HeaderInfo.html`` (``banner`` and ``docked``)
lost its "Status:" prefix and shows assignee, comments and to-dos as text
buttons instead of icon buttons with count pills. The status strip above a
content element moved into its own template,
``Backend/Header/StatusStrip.html``, and no longer uses ``HeaderInfo.html``.
Content elements no longer offer the watch toggle in the strip, the record
modal still does. If you override one of these templates, compare it with the
new version.

**Lists.** Rows in the record list and the file list carry a status edge on
their first cell instead of a tinted row, and the status dropdown shows the
status name (or "Set status"). The folder banner in the file list only appears
in ``banner`` mode.

**Labels.** Several labels changed to match the terminology of the design
rules ("Unassigned" instead of "-- Not assigned --", "No status" instead of
"-- stateless --", "to-dos" instead of "ToDo(s)", "Remove status" instead of
"Reset Status"). The CLI user is shown as "System". Translations of the
changed keys have to be adjusted if you override them.
