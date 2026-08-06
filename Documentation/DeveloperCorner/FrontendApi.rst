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

..  note::
    Endpoint contracts are documented alongside their implementation. This
    section grows as the individual endpoints land.

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

        const response = await fetch(url, { … });
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
