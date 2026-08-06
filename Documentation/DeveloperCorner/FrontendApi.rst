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
