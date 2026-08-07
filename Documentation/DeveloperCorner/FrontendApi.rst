..  include:: /Includes.rst.txt

..  _frontend-api:

============
Frontend API
============

Beside the backend UI, the content planner can expose a small set of endpoints
meant to be consumed from other contexts — most notably a frontend editing
sidebar that renders status badges and comment counters next to the elements
they belong to.

The whole surface is **disabled by default**. A stock installation behaves
exactly as it did before these endpoints existed.

..  _frontend-api-flags:

Feature flags
=============

Two independent :ref:`extension configuration <extension-configuration>` flags
control availability, both defaulting to ``0``:

:ref:`enableFrontendApi <extconf-enableFrontendApi>`
    Enables the JSON endpoints for status changes, the status selection and the
    batched annotation summary.

:ref:`enableEmbeddableCommentsView <extconf-enableEmbeddableCommentsView>`
    Enables the embeddable comments view, a backend route that renders a comment
    thread as a complete HTML document suitable for an ``<iframe>``.

They are separate on purpose: the embeddable comments view is useful
backend-internally as well, so it should not require opening up the JSON API.

..  _frontend-api-security:

Security model
==============

..  important::
    The flags control **availability only**. They never replace authentication.

Every endpoint stays a regular TYPO3 **backend route**, which means each request
is subject to the same checks as any other backend AJAX call:

Backend session
    The request needs an authenticated backend user. There is no anonymous
    access and no separate token mechanism — a frontend consumer has to run in a
    context where the backend session cookie is present.

CSRF route token
    Backend routes are protected by the core's route token mechanism. Consumers
    must obtain a valid URI (including its token) rather than assembling the
    path by hand.

Content planner permissions
    On top of that, every endpoint applies the extension's own checks —
    :ref:`visibility and per-record access <permissions>`, and the relevant
    status and comment permissions. An endpoint never grants more than the
    equivalent action in the backend UI would.

Because the flags default to off and an unset flag is treated as *off*, the
surface fails closed: an installation that upgrades without touching its
configuration keeps the endpoints disabled.

..  note::
    Newly introduced flags are written to the configuration file only once the
    :guilabel:`Extension Configuration` form is saved. Until then the value is
    absent, which is evaluated as disabled.

..  _frontend-api-endpoints:

Endpoints
=========

Routes are registered only while their flag is on, so a disabled endpoint does
not exist in the route collection at all.

..  warning::
    A request to an unregistered backend route is **not** answered with a 404.
    TYPO3 redirects it to the backend shell, so a consumer receives a ``200``
    carrying an HTML document instead of JSON.

    This is the same response shape a consumer already gets when the backend
    session has expired, so it has to verify the response content type rather
    than trust the status code:

    ..  code-block:: js

        const response = await fetch(url);
        if (!response.headers.get('content-type')?.includes('application/json')) {
            // endpoint disabled, or the backend session is gone
        }

..  _frontend-api-summary:

Annotation summary
------------------

..  code-block:: none

    POST /content-planner/api/summary

Returns status, assignee and comment counters for many records in **one**
request — the call a consumer needs to render badges for a whole page: the
``pages`` record plus all of its content elements.

Request body:

..  code-block:: json

    {
      "items": [
        { "table": "pages", "uid": 12 },
        { "table": "tt_content", "uid": 34 }
      ]
    }

Response:

..  code-block:: json

    {
      "items": [
        {
          "table": "pages",
          "uid": 12,
          "status": {
            "uid": 2,
            "title": "In Progress",
            "colorName": "yellow",
            "colorHex": "#ffcd75",
            "iconIdentifier": "flag-yellow"
          },
          "assignee": { "uid": 3, "displayName": "Jane Doe (jane)" },
          "comments": { "total": 2, "todoTotal": 3, "todoResolved": 1 },
          "capabilities": {
            "canChangeStatus": true,
            "canUnsetStatus": true,
            "canComment": true
          }
        }
      ]
    }

``status`` and ``assignee`` are ``null`` when the record carries none, which is
cheap for a consumer to skip.

Contract notes:

No markup
    The response never contains rendered HTML or pre-escaped entities. Icons are
    reported as **identifiers** and colours as hex values; rendering and escaping
    are the consumer's job. The backend keeps its own HTML-bearing DTO
    (``StatusItem``), which is deliberately left untouched.

Silent omission
    Records the current user may not see — a table they lack access to, a page
    outside their mounts, a uid that does not exist — are **left out** of
    ``items`` rather than reported as an error, so one forbidden element cannot
    spoil a whole page's request. Consumers must not assume the response has the
    same length or order as the request; match on ``table`` plus ``uid``.

Counter semantics
    ``comments.total`` counts *open* comments including replies, exactly like the
    backend badge. To-do counters are zero while
    :ref:`commentTodos <extconf-commentTodos>` is off, again matching the
    backend.

Batch limit
    At most 500 items per request; a larger batch is rejected with 400. A page's
    worth of content elements stays far below that.

Capabilities
    Advisory, for hiding controls a user cannot use. They are never the
    enforcement — every write endpoint re-checks permissions itself.

    There is no "may view comments" flag on purpose: the extension has no such
    permission, so it could only ever be ``true``, and a field that never varies
    suggests a check that does not exist. Being able to read a record's comments
    follows from the visibility check applied to the whole request — if a record
    appears in ``items`` at all, its comments are readable.

..  _frontend-api-status:

Status change
-------------

..  code-block:: none

    POST /content-planner/api/status

..  code-block:: json

    { "table": "pages", "uid": 12, "status": 2 }

``status: null`` unsets it. The key must be present — omitting it is a 400, not a
reset.

``table``, ``uid`` and ``status`` are **validated, not cast**. Anything that is not
a positive integer — ``0``, ``"abc"``, ``"2abc"``, ``true``, ``2.5`` — is answered
with 400 and changes nothing. A cast would make ``"abc"`` an implicit reset and
``"2abc"`` a real status change. Digit strings such as ``"2"`` stay acceptable, so
a form-encoded body keeps working.

Response on success:

..  code-block:: json

    {
      "success": true,
      "title": "Status changed",
      "message": "The status of the record has been changed successfully.",
      "severity": 0,
      "record": { "table": "pages", "uid": 12, "status": { "…": "…" } }
    }

``record`` is the same shape as one item of the
:ref:`annotation summary <frontend-api-summary>`, so a consumer can re-render a
badge without a second request.

Contract notes:

It goes through the DataHandler
    The change is applied with a data map, not by writing the field. That is what
    makes it indistinguishable from a change made in the backend UI: permission
    stripping, the status reset handling, auto assignment, the comment relation
    sync and :ref:`StatusChangeEvent <events>` all run. A direct write would skip
    every one of them.

403 means the change was stripped
    ``StatusChangeManager`` does not raise an error when a permission check fails
    — it silently removes the fields. The endpoint therefore decides the outcome
    by **re-reading the record** rather than by re-implementing the checks. When
    the stored status does not match the requested one, the response is a 403
    carrying the failure message and **no** ``record`` key.

Wording follows the action
    A change resolves ``status.changed``, a reset ``status.reset`` — the same
    catalogue entries the backend flash messages use, so the frontend cannot drift
    from the backend wording.

404 hides existence
    An unknown uid, an unregistered table and a record the user may not access all
    answer 404, so the endpoint does not reveal which of the three applied.

..  _frontend-api-status-selection:

Status selection
----------------

..  code-block:: none

    GET /content-planner/api/status-selection?table=pages&uid=12

Returns the statuses selectable for one record, so a frontend dropdown offers
exactly what the backend offers.

..  code-block:: json

    {
      "table": "pages",
      "uid": 12,
      "items": [
        {
          "uid": 1,
          "title": "Draft",
          "colorName": "blue",
          "colorHex": "#64bbc8",
          "iconIdentifier": "flag-blue",
          "current": false
        }
      ],
      "canUnset": true
    }

Responds 400 without both parameters, 403 when the content planner is not
visible for the user, and 404 for a record that does not exist or that the user
may not read.

Contract notes:

The selection event runs
    The list is passed through
    :ref:`PrepareStatusSelectionEvent <events>` just like every backend menu, so a
    project listener that restricts statuses restricts this response identically.

    The selection handed to the event is **keyed by status uid** — the convention
    all backend selection builders already follow. A listener unsetting a status
    by key therefore works unchanged. A listener that rewrites entry *values* has
    to branch on ``$event->getContext()``, which it must do anyway, since the
    value shape already differs between the list, dropdown and context-menu
    builders. Entries a listener *adds* under keys that were not offered as
    statuses are ignored.

Group restrictions apply
    Candidates are collected the same way as
    ``AbstractSelectionService::addAllStatusItems()``, so ``allowed_statuses``
    from :ref:`be_groups <permissions>` is honoured.

The current status is included
    Unlike the backend menus, which omit the active status because picking it
    again is a no-op, the response reports it with ``current: true`` — a consumer
    needs it to render the selected entry.

``canUnset``
    True only when the record actually has a status *and* the user holds the
    unset permission. It is independent of the selection listener.

..  _frontend-api-comments-view:

Embeddable comments view
------------------------

..  code-block:: none

    GET /content-planner/comments/view?table=pages&uid=12

Renders the comment thread of one record as a **complete HTML document**, suitable as the
``src`` of an ``<iframe>``. Unlike the endpoints above this is gated by
:ref:`enableEmbeddableCommentsView <extconf-enableEmbeddableCommentsView>`, and it answers
with a document rather than JSON — including when it refuses, since the response is going
to be displayed in somebody else's frame. Refusals mirror the backend's own comment
endpoint: ``400`` without both parameters, ``403`` when the content planner is not visible
for the user, ``404`` for a record that does not exist or is not tracked.

Optional query parameters ``sortComments`` (``ASC``/``DESC``) and ``showResolvedComments``
(``0``/``1``) carry the filter state; the view's own filter controls are links that reload
it with the flipped value.

Contract notes:

Self-contained, and carrying no JavaScript
    The document brings its own stylesheets — the backend's ``backend.css`` plus the
    content planner's comment styles — so the embedding page needs no backend assets. It
    ships **no JavaScript at all**, which is a deliberate constraint rather than an
    omission: an embedded frame's ``top`` window belongs to the consumer, so nothing may
    depend on ``top.TYPO3``, on ``TYPO3.settings`` or on the backend's modal machinery.

    ..  important::
        For the same reason the view does not use ``PageRenderer``. Rendering a backend
        document through it inlines ``TYPO3.settings``, which contains a URL *including its
        CSRF token* for every registered backend AJAX route. This view needs none of them,
        so it assembles its own document and mirrors only the ``<html>`` attributes
        (language, direction, theme, colour scheme) that the backend styling depends on.

..  note::
    **The document is not token-free, and it is not meant to be.** The comment actions
    are ``record_edit`` and ``tce_db`` links, and a backend route link carries a route
    token by construction — removing it would remove the action. Avoiding ``PageRenderer``
    narrows what the document exposes from *every* backend AJAX route to the handful this
    view actually offers; it does not eliminate exposure.

    Treat that as reducing blast radius rather than closing a hole. A cross-origin
    embedder can read neither the frame's DOM nor its globals, and a same-origin embedder
    already holds the backend session and could fetch any token it wanted — so neither
    case is an escalation. The reason to keep the surface small is everything else a
    response passes through: caches, proxy logs, browser history, screenshots.

Dark and light follow the backend user
    The colour scheme is taken from the backend user's own preference, the same way the
    backend resolves it. With the preference left on *auto* no attribute is emitted and the
    document follows ``prefers-color-scheme``.

Actions are link round-trips
    Creating, editing and replying keep using ``record_edit`` — so the RTE and every TCA
    field behave exactly as in the backend — and navigate **inside the frame**, returning to
    the view through ``returnUrl``. Resolving goes through ``tce_db``, which redirects back
    on its own.

    In the edit form the user saves and then closes, which is the plain backend flow: the
    core :guilabel:`Close` button is what follows the ``returnUrl``. The backend modal
    normally has that button removed, because it closes the form itself — the extension
    detects this flow by the ``returnUrl`` and keeps the button, since here nothing else
    would bring the user back.

Links that leave the thread open outside the frame
    The share link and the record link would replace the frame with a full backend page, so
    both carry ``target="_top"``.

Deleting is not offered here
    A delete link cannot ask for confirmation without JavaScript, and a single click that
    irreversibly removes a comment is worse than not offering it. Use the record link to
    reach the backend, where deletion keeps its confirmation step.

..  warning::
    **Embedding works same-origin out of the box; cross-origin does not.** The view is a
    backend route and needs the backend session cookie, which TYPO3 issues with
    ``SameSite=strict`` by default — a browser does not send it to an ``<iframe>`` on a
    different origin, so the frame ends up at the login screen instead.

    Embedding a frontend on the same host as the backend — the motivating case — is
    unaffected. Embedding from a different origin requires
    :php:`$GLOBALS['TYPO3_CONF_VARS']['BE']['cookieSameSite'] = 'none'` (which also demands
    ``Secure``). Weigh that against its CSRF implications before changing it; it is a
    site-wide decision, not one this view can make.

Checking a flag
===============

Both flags are available through :ref:`ExtensionUtility <extension_utility>`:

..  code-block:: php

    use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

    if (ExtensionUtility::isFrontendApiEnabled()) {
        // …
    }

    if (ExtensionUtility::isEmbeddableCommentsViewEnabled()) {
        // …
    }
