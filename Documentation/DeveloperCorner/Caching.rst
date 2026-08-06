..  include:: /Includes.rst.txt

..  _caching:

=======
Caching
=======

The extension owns one cache frontend, ``ximatypo3contentplanner_cache``, registered
in ``Configuration::registerCache()``. It is deliberately small: most reads go
straight to the database, and only two repositories cache anything at all.

..  _caching-keys:

What is cached
==============

..  confval:: <ext>--<table>--p<pid>
    :name: cache-key-record-listing

    Written by ``RecordRepository::findByPid()``. Holds the content planner records
    of one page, and is the **only** cached record read.

    Tagged with one ``<table>_<uid>`` per contained row, plus
    ``<table>__pageId__<pid>``. The per-row tags are what make invalidation
    possible without knowing which listing a record ended up in.

..  confval:: <ext>--status--all
    :name: cache-key-status-all

    Written by ``StatusRepository::findAll()``. Tagged
    ``tx_ximatypo3contentplanner_domain_model_status_<uid>`` per status.

..  confval:: <ext>--status--<uid>
    :name: cache-key-status-single

    Written by ``StatusRepository::findByUid()``, same tagging.

``<ext>`` is ``Configuration::CACHE_IDENTIFIER`` (``ximatypo3contentplanner``).

..  note::
    ``RecordRepository::findByUid()`` and ``findAllByUids()`` are **not** cached, and
    neither are the comment counters or assignee names. The
    :ref:`annotation summary endpoint <frontend-api-summary>` therefore reads records,
    counts and names fresh on every request; its only cache dependency is the status
    entity.

..  _caching-invalidation:

How it is invalidated
=====================

There are two mechanisms, and knowing which applies to a write is the whole game.

Writes through the DataHandler
    ``DataHandlerHook::clearCachePostProc()`` forwards the tags the core hands it and
    adds ``<table>__pageId__<uid_page>``. Core supplies ``<table>`` and
    ``<table>_<uid>``, so a record edited in the backend drops the listings holding it
    automatically. Nothing extra is needed on these paths.

Raw writes
    A ``QueryBuilder`` UPDATE never reaches the DataHandler, so **no invalidation
    happens by itself**. These paths have to call
    ``RecordRepository::flushCacheForRecord($table, $uid)``, which flushes the
    ``<table>_<uid>`` tag and thereby every listing containing that record —
    the caller does not need to know the record's pid.

..  warning::
    When adding a raw write to a content planner field, invalidate explicitly. A
    missing flush is invisible in the backend until a page listing happens to be warm,
    which makes it a bug that only shows up under load.

Covered raw-write paths
-----------------------

``RecordRepository::updateStatusByUid()``
    Flushes the record's tag. Reached from the bulk update command and
    ``PlannerUtility::updateStatusOfRecord()``.

``RecordRepository::updateCommentsRelationByRecord()``
    Flushes the record's tag. This one matters even though it runs *inside* a
    DataHandler request: the surrounding flush only carries comment-table tags, so
    without it a cached listing keeps serving the previous comment count.

..  _caching-known-gaps:

Known gaps
==========

The following raw writes still perform no invalidation. They mutate records whose
listings may be cached, so a stale read is possible until the entry expires or an
unrelated DataHandler write drops it:

- ``StatusChangeManager::clearStatusOfExtensionRecords()`` — a mass UPDATE across a
  whole table, so it cannot name the affected uids; it needs a broader flush than the
  per-record tag offers.
- ``CommentRepository::deleteAllCommentsByRecord()`` — also zeroes the host record's
  comment count.
- ``CommentRepository::deleteRepliesByParentUid()``.
- ``FolderStatusRepository::createOrUpdate()``, reached from
  ``FolderController::updateStatusAction()``.

Closing these requires the cache frontend in classes that do not have it today, which
is a larger change than the invalidation fixes above.
