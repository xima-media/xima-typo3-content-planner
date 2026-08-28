..  include:: /Includes.rst.txt

..  _page-tree-integration:

=======================
Page tree integration
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

The page tree status labels (and the optional comment count, see
:ref:`treeStatusInformation <extconf-treeStatusInformation>`) need the status and
comment count of every visible page tree node. This page documents how the
Content Planner gets that data onto the tree and, importantly, what that
costs in terms of compatibility with other extensions.

Why an XCLASS is used
======================

``Configuration::overrideClasses()`` XCLASSes core's
``TYPO3\CMS\Backend\Controller\Page\TreeController`` to override its
``initializePageTreeRepository()`` method. The only change made there is
passing the extension's ``FIELD_STATUS`` and ``FIELD_COMMENTS`` column names
as ``$additionalFieldsToQuery`` into ``PageTreeRepository``, instead of the
empty array core passes by default:

..  code-block:: php
    :caption: Classes/Controller/TreeController.php (excerpt)

    $pageTreeRepository = GeneralUtility::makeInstance(
        PageTreeRepository::class,
        $backendUser->workspace,
        [Configuration::FIELD_STATUS, Configuration::FIELD_COMMENTS],
        $additionalQueryRestrictions,
    );

This makes both columns available on every tree node's ``_page`` array so
``AfterPageTreeItemsPreparedListener`` (a regular PSR-14 event listener, no
XCLASS involved) can read them straight off the node it is already
processing.

What breaks without it
=======================

Without the extra fields in the initial page tree query, the status and
comment columns simply are not part of the result set the tree is built
from, and there is no supported way to add them after the fact:

*   ``AfterPageTreeItemsPreparedEvent`` fires once the tree controller has
    already assembled the full node list from a query whose field list was
    fixed at query time. Modifying ``$item`` in the listener cannot make a
    column reappear that was never selected.
*   ``AfterRawPageRowPreparedEvent`` fires per page row, but also only after
    that row has already been fetched with the same fixed field list.

The only way to recover the status/comment values from either event would be
an additional per-node database lookup, i.e. one extra query for every page
in the tree (N+1), which does not scale to trees with hundreds of pages.

Why this was not solved differently for v14
=============================================

This extension targets TYPO3 13.4+ and 14.0+, so a version-gated
event-based path (the same pattern used for
``AfterFileStorageTreeItemsPreparedListener``, which only takes effect on
v14+) was considered. It does not apply here: ``TreeController::
initializePageTreeRepository()`` and ``PageTreeRepository::__construct()``
were compared directly between the installed TYPO3 v13.4.33 and v14.3.6
sources and are byte-identical. Neither version introduced a supported way
to widen the fetched field list, nor a new event that runs before the query
is built. The file storage tree case is different: the folder tree already
identifies each node by its combined storage/folder identifier, so status
data can be attached from a per-node lookup without needing extra columns in
core's own query.

`TYPO3 Forge #97259 <https://forge.typo3.org/issues/97259>`__ tracks a core
change to make the page tree's fetched fields extensible without XCLASSing
the controller. That is the clean long-term fix and is being pursued
upstream separately; this extension is not patching TYPO3 core itself. Once
a core release ships that capability, this XCLASS can be dropped.

Conflict potential
===================

..  warning::
    ``$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\
    Controller\Page\TreeController::class]`` accepts exactly one
    registrant. If another installed extension also XCLASSes core's page
    tree ``TreeController``, one of the two overrides silently wins and the
    other is lost, there is no merging or chaining. This is a realistic
    conflict for any extension that customizes page tree fetching, e.g. page
    tree facets/filtering extensions.

If you run into this conflict, the practical options are:

*   Coordinate with the other extension so only one of the two actually
    XCLASSes ``TreeController``, with the other consuming
    ``AfterPageTreeItemsPreparedEvent`` (or, if it also needs additional
    fields, contributing them via that same override) instead of XCLASSing
    it a second time.
*   Track and adopt the fix in `TYPO3 Forge #97259
    <https://forge.typo3.org/issues/97259>`__ once it is available, which
    removes the need for this XCLASS entirely.

The override itself is intentionally minimal, see
``Classes/Controller/TreeController.php``: only
``initializePageTreeRepository()`` is overridden, and every difference from
core's implementation is documented inline.
