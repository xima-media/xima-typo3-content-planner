..  include:: /Includes.rst.txt

..  _notifications:

=======================
Notifications
=======================

..  contents:: Table of Contents
    :local:
    :depth: 2

..  note::

    Added in 3.1.0. This page documents the dispatch layer built for issue `#300
    <https://github.com/xima-media/xima-typo3-content-planner/issues/300>`__, the backend
    toolbar notification center built for issue `#301
    <https://github.com/xima-media/xima-typo3-content-planner/issues/301>`__, and the email
    digest built for issue `#302 <https://github.com/xima-media/xima-typo3-content-planner/issues/302>`__.

Overview
========

When a watched record changes, :php:`NotificationDispatcher` resolves the record's active
watchers (see :ref:`Events <events>` for how a user becomes a watcher), excludes the acting
backend user, and hands one :php:`Notification` value object per remaining recipient to every
registered channel that wants to handle it:

..  code-block:: text

    PSR-14 event → EventListener/Notification/* → NotificationDispatcher
      → WatcherService::getActiveWatchersWithSource()
      → minus actor
      → foreach recipient: foreach channel where supports(): deliver()

Three PSR-14 events currently feed the dispatcher, each via its own listener in
`Classes/EventListener/Notification/ <https://github.com/xima-media/xima-typo3-content-planner/tree/main/Classes/EventListener/Notification>`__:

=============================  ===============================
`event_type` value             Triggering PSR-14 event
=============================  ===============================
`status_changed`                StatusChangeEvent
`assigned`                      AssigneeChangedEvent
`comment_added`                 CommentCreatedEvent
=============================  ===============================

Reason codes
============

Every stored notification carries a machine-readable `reason`, so a channel can render a
"why you receive this" line without re-deriving it. The reason is mapped purely from the
recipient's :php:`WatchSource` (see :php:`NotificationReason::fromWatchSource()`) - the
triggering event type does not further refine it, since a recipient's reason for watching a
record does not change per event:

=============================  ================================================
`WatchSource`                  `NotificationReason`
=============================  ================================================
`Assignment`                    `watching_since_assignment`
`Comment`                       `watching_since_comment`
`StatusChange`                  `watching_since_status_change`
`Manual`                        `watching_manually`
`Mention` (reserved, see #305)  `mentioned`
=============================  ================================================

Payload
=======

The `payload` column is a JSON-encoded, defensively versioned array built by
`NotificationPayloadFactory <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/NotificationPayloadFactory.php>`__.
Every payload carries a `version` key and a `title` **snapshot**, resolved eagerly at dispatch
time via :php:`RecordTitleResolver` rather than lazily when a notification is later rendered.
This is what keeps a notification renderable after its record has since been renamed or
deleted. Consumers must tolerate unknown or additional keys, since the payload shape may grow
as new event types are added.

Adding a channel
================

Third-party extensions add a channel by implementing :php:`NotificationChannelInterface` and
tagging their service with `xima_typo3_content_planner.notification_channel` - the same DI-tag
extensibility pattern used for dashboard widgets (`dashboard.widget`) elsewhere in this
extension:

..  code-block:: php
    :caption: Classes/Notification/SlackChannel.php

    <?php
    namespace MyVendor\MyExtension\Notification;

    use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;
    use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationChannelInterface;

    final class SlackChannel implements NotificationChannelInterface
    {
        public function supports(Notification $notification): bool
        {
            // e.g. gated behind your own extension configuration toggle
            return true;
        }

        public function deliver(Notification $notification): void
        {
            // send to Slack
        }
    }

..  code-block:: yaml
    :caption: Configuration/Services.yaml

    MyVendor\MyExtension\Notification\SlackChannel:
      tags:
        - name: xima_typo3_content_planner.notification_channel

The built-in `DatabaseChannel` follows the same contract: it persists the notification row and
is gated behind the `notificationChannelDatabase` extension configuration toggle (see the
"notifications" category in the extension configuration).

..  warning::
    **A watcher relation is not a read permission.** The dispatcher only drops recipients
    whose account is deleted or disabled; it deliberately does not check per-recipient record
    access, because it runs inside the save request and resolving another user's permissions
    per watcher would put that cost on every save.

    A channel therefore has to decide for itself whether the recipient may see what it is
    about to expose, and *when*. Permissions can change between dispatch and delivery, and a
    title snapshot in the payload does not expire.

    -   Channels that render inside the backend for the logged-in recipient (the notification
        centre, dashboard widgets) can check at render time, where the current backend user
        *is* the recipient - see
        :php:`NotificationCenterDataProvider`, which falls back to a generic label without a
        link when access is denied.
    -   Channels that deliver outside the backend (e-mail, chat) have no such context. They
        must resolve the recipient's own permissions explicitly. Note that
        :php:`PermissionUtility::checkAccessForRecord()` returns `true` unconditionally for
        the `_cli_` user, so calling it from a scheduler command checks nothing.

Bulk and CLI semantics
=======================

Dispatching one notification per recipient per record makes a mass operation over many records
a potential notification storm. This extension addresses it on two levels:

#.  **The three raw-SQL bypass paths documented in** :ref:`Events <events>` **stay silent, and
    that decision is unchanged by this feature.** `RecordRepository::updateStatusByUid()`
    (used by `BulkUpdateCommand` and, via `PlannerService::updateStatusForRecord()`, by
    third-party integrations) and `StatusChangeManager::clearStatusOfExtensionRecords()` never
    dispatch `StatusChangeEvent`/`AssigneeChangedEvent` in the first place, so
    `NotificationDispatcher` - being purely event-driven - never runs for them. No separate
    notification-specific suppression was needed to make these paths storm-safe.
#.  For any *other* CLI or migration code path that does dispatch these PSR-14 events directly
    (e.g. via the DataHandler), `NotificationSuppressionState` is a shared, process-wide pause
    switch: call `pause()` before the bulk operation and `resume()` in a `finally` block
    afterwards, and `NotificationDispatcher::dispatch()` becomes a no-op for the duration.
    `BulkUpdateCommand`'s `--no-notify` option wires this switch for operator clarity, even
    though it is a defensive no-op today given point 1 above.

**Actor in a CLI context:** `StatusChangeEvent`/`AssigneeChangedEvent`/`CommentCreatedEvent` all
accept a nullable actor UID, and `NotificationDispatcher` never excludes a `null` actor from the
recipient list (there is no watcher with UID `null` to match). This is intentional: a CLI
context without an authenticated backend user has no "real" actor to attribute the action to,
and no auto-watch trigger fires either (see the `AutoWatchOn*Listener` classes' early returns on
a `null` actor). A future CLI command that *does* dispatch these events directly should expose
its own `--actor` option threaded into the event's actor argument if attribution matters; there
is no dedicated "system" placeholder actor, since no current CLI path needs one.

Email digest
============

`content-planner:notification:digest` (issue #302) is a schedulable Symfony command - wire it
into TYPO3 scheduler or an external cron (recommended: daily). It sends **at most one email per
recipient per run**, summarizing every notification of theirs with `digested_at IS NULL`. It
deliberately does *not* filter out already-read notifications: reading one in the toolbar (see
above) must not silently drop it from the digest, since the two tracks (`read_at`, `digested_at`)
answer different questions ("have I seen this in the backend?" vs. "was this ever mailed to me?").

..  code-block:: bash

    vendor/bin/typo3 content-planner:notification:digest
    vendor/bin/typo3 content-planner:notification:digest --dry-run

`--dry-run` prints a per-recipient summary (notification/record counts) without sending any mail
or touching `digested_at`.

Dedup
-----

`DigestGroupBuilder <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Digest/DigestGroupBuilder.php>`__
groups a recipient's pending notifications by `(tablename, record_uid)` and collapses every
group into **one line per event type**, not one line per notification. For `status_changed` and
`assigned` events this is a deduped transition chain built from the chronological
`previousStatus`/`newStatus` (or `previousAssignee`/`newAssignee`) pairs: consecutive duplicate
states collapse into one, so five status changes on the same record - even with no-op
transitions in between - render as a single line such as `Status: Draft → Review → Approved`,
with the *actual* event count (`5`, here) preserved separately for the summary. `comment_added`
events instead surface the latest comment's excerpt plus a count.

Recipient validation and opt-out
---------------------------------

`DigestService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Digest/DigestService.php>`__
skips a recipient (leaving their notifications untouched, so a later run or opt-in can still pick
them up) when:

-   the backend user no longer exists, or is deleted/disabled
-   `tx_ximatypo3contentplanner_digest` (the "Receive content planner email digest" toggle in
    User Settings, default **on**) is off
-   the user has no valid email address - logged via PSR-3 (`LoggerAwareInterface`) and reported
    in the command's summary, per the issue's "handle gracefully" acceptance criterion

A per-recipient transport failure (e.g. the mail server being unreachable) is caught in
`EmailDigestCommand` and does not abort the run for the remaining recipients; that recipient's
notifications simply stay non-digested and are retried on the next run. The command's exit code
is `Command::FAILURE` whenever at least one recipient failed this way, so a scheduled run
surfaces the problem instead of silently swallowing it.

`digested_at` is set via a single atomic `UPDATE ... WHERE uid IN (:uids) AND backend_user = :uid
AND digested_at IS NULL` (see `NotificationRepository::markDigestedByUids()
<https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Domain/Repository/NotificationRepository.php>`__),
scoped to exactly the notification uids that run rendered into a mail - and only executed *after*
that mail was actually sent, so a crash or transport failure between the two leaves the
notifications pending rather than losing them. No explicit database transaction is needed: the
single statement is itself atomic, and it is always scoped to one recipient's own rows.

Language and backend deep links
--------------------------------

The mail is rendered with `$GLOBALS['LANG']` pointed at the recipient's own backend language
(`be_users.lang`) for the whole duration of grouping and sending, so every resolved label -
including the "Unassigned" placeholder - comes out in the right language, independent of the CLI
process's own locale.

Every record link is built via the same `UrlUtility::getRecordLink()
<https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Utility/Routing/UrlUtility.php>`__
used elsewhere in the extension, which - lacking a real HTTP request in a CLI context - falls
back to TYPO3's own `RequestContext('/typo3/')` and therefore returns a *relative* backend path.
Since a relative path is useless in an email, `DigestMailFactory` prepends the
`notificationDigestBackendBaseUrl` extension configuration value (empty by default) to build an
absolute URL; leave it unset to accept relative links (they still work if the recipient is
already logged into the same backend host in their browser) or set it to your backend's public
base URL (e.g. `https://example.com`) for links that work from a cold inbox.

Template override
-----------------

The default templates live in `Resources/Private/Templates/Mail/NotificationDigest.html` and
`.txt` (both formats are needed: `TYPO3\CMS\Core\Mail\FluidEmail` renders HTML and plain text by
default). Override either by registering your own, higher-priority template root path:

..  code-block:: php
    :caption: ext_localconf.php (your extension)

    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][] = 'EXT:my_extension/Resources/Private/Templates/Mail/';

Since this extension registers its own path with `[]` (auto-incrementing array key) from its own
`ext_localconf.php`, and TYPO3 loads extensions in dependency order, an override registered the
same way by any extension depending on this one is appended at a numerically higher key and wins
- `TemplatePaths` resolves a given template name against the highest-priority path that defines
it first. See `Configuration::registerMailTemplates()
<https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Configuration.php>`__.

Workspaces
==========

`StatusChangeEvent`/`AssigneeChangedEvent`/`CommentCreatedEvent` are all dispatched from inside
`DataHandlerHook`, which runs on every save - including a save into a workspace, i.e. on
creating/editing a versioned draft record. None of them are gated on that draft later being
published. Notifications for planner events on versioned records therefore fire **on save, not
on publish**. This is a deliberate decision, not an oversight: revisiting it (e.g. to defer
notification until publish) would need new publish-side wiring and is left to whichever future
issue needs it (see issue #309), rather than speculatively built here.

..  seealso::

    View the sources on GitHub:

    -   `NotificationChannelInterface <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/NotificationChannelInterface.php>`__
    -   `NotificationDispatcher <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/NotificationDispatcher.php>`__
    -   `NotificationReason <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Domain/Model/NotificationReason.php>`__
    -   `NotificationSuppressionState <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/NotificationSuppressionState.php>`__
    -   `EmailDigestCommand <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Command/EmailDigestCommand.php>`__
    -   `DigestService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Digest/DigestService.php>`__
    -   `DigestGroupBuilder <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Digest/DigestGroupBuilder.php>`__
    -   `DigestMailFactory <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Digest/DigestMailFactory.php>`__
