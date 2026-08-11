..  include:: /Includes.rst.txt

..  _pagetree-facets:

=============================
Pagetree Facets (TYPO3 v14+)
=============================

..  versionadded:: 2.4.0

    Integration with `konradmichalik/typo3-pagetree-facets
    <https://github.com/konradmichalik/typo3-pagetree-facets>`__ was
    introduced in version 2.4.

If the optional `konradmichalik/typo3-pagetree-facets
<https://packagist.org/packages/konradmichalik/typo3-pagetree-facets>`__
extension is installed on TYPO3 v14 and
:ref:`enablePagetreeFacetsIntegration <extconf-enablePagetreeFacetsIntegration>`
is enabled, a **Content Planner** facet appears in the page tree filter modal.

..  note::
    ``typo3-pagetree-facets`` itself requires PHP 8.3+, stricter than this
    extension's PHP 8.2+ floor - installing it on a PHP 8.2 environment is
    rejected by Composer independent of this integration.

..  contents:: Table of Contents
    :local:
    :depth: 2

Tokens
======

..  list-table::
    :header-rows: 1
    :widths: 20 45 35

    * - Token
      - Values
      - Notes
    * - ``status:``
      - status uid(s), comma-separated for OR, ``none``
      - Only statuses allowed for the current user (see :ref:`permissions`) are offered or matched; a disallowed uid resolves to no match, silently.
    * - ``assignee:``
      - ``me``, a backend user uid, ``none``
      - ``none`` means unassigned.
    * - ``comments:``
      - ``open``, ``resolved``, ``todo``, ``mine``, ``none``
      - ``todo`` only appears when :ref:`commentTodos <extconf-commentTodos>` is enabled.

Values within one token combine with OR (``status:2,3``); separate tokens
combine with AND (``status:2 assignee:me``), same as every other facet in
this filter modal.

Other Registered Records
========================

Other registered records (such as content elements if enabled via
:ref:`enableContentElementSupport <extconf-enableContentElementSupport>`,
plus files/folders if :ref:`enableFilelistSupport <extconf-enableFilelistSupport>` is enabled,
and any additional records registered via ``ExtensionUtility::getRecordTables()``)
can carry their own status, assignee and comments. However, the page tree filter
only ever returns pages. All three tokens therefore match a page whose other
registered records meet the criteria as well as pages that match directly —
controlled by the modal's **"Include content elements"** checkbox, checked by
default. Unticking it restricts every token to page-level matches only.

..  note::
    This checkbox only has an effect once at least one status, assignee or
    comment criterion is also selected in this facet — it modifies how those
    tokens match, rather than being a filter criterion of its own. With
    nothing else selected, the filter modal's underlying framework never
    calls this facet at all, so toggling the checkbox alone has nothing to
    persist and appears to reset the next time the modal opens.

Out of scope
============

- Slug-based ``status:`` tokens (uid-only for now)
- Filtering the file storage tree
- TYPO3 v13 (the underlying core event does not exist there)
