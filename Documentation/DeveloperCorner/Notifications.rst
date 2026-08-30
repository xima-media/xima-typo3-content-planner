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
    <https://github.com/xima-media/xima-typo3-content-planner/issues/301>`__, the email
    digest built for issue `#302 <https://github.com/xima-media/xima-typo3-content-planner/issues/302>`__,
    the retention/cleanup command built for issue `#304
    <https://github.com/xima-media/xima-typo3-content-planner/issues/304>`__, the immediate
    email channel built for issue `#306 <https://github.com/xima-media/xima-typo3-content-planner/issues/306>`__,
    content change notifications built for issue `#309
    <https://github.com/xima-media/xima-typo3-content-planner/issues/309>`__, and @-mentions
    built for issue `#305 <https://github.com/xima-media/xima-typo3-content-planner/issues/305>`__.

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
`content_changed`               *(none - see below)*
=============================  ===============================

`content_changed` (issue #309) is the exception to the "PSR-14 event → listener → dispatch" flow
above: it is dispatched directly from `DataHandlerHook` rather than from a domain event, and its
rows are *aggregated in place* rather than inserted one per occurrence - see
:ref:`Content change notifications <content-change-notifications>` below.

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
`Mention` (see #305)            `mentioned`
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

Immediate email channel
=======================

`ImmediateEmailChannel <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Channel/ImmediateEmailChannel.php>`__
(issue #306) is the notification feature's second and final user-facing email setting: a
recipient's per-event alternative to the daily digest above. Both settings live in the same
"Content Planner" User Settings tab:

-   `tx_ximatypo3contentplanner_digest` (#302) - receive content planner email at all
-   `tx_ximatypo3contentplanner_immediate_email` (#306) - when the above is on, deliver
    per-event and throttled instead of batched once a day

A recipient with the second toggle on is skipped by `DigestService` (see
`DigestService::prefersImmediateEmail()`) so they are never notified twice for the same event -
the two channels are mutually exclusive per recipient, not additive.

Throttle
--------

`ImmediateEmailService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Immediate/ImmediateEmailService.php>`__
enforces "at most one mail per `(recipient, record)` pair every 15 minutes, batching whatever
arrived in between" using its own `tx_ximatypo3contentplanner_immediate_queue` table (deliberately
separate from the digest's `tx_ximatypo3contentplanner_notification` table, so the two channels
never share bookkeeping):

#.  every incoming event is enqueued unconditionally
#.  if no batch was ever sent for that `(recipient, record)` pair, or the last one was sent 15
    minutes ago or longer, every not-yet-sent row for that pair (the new event plus anything still
    queued) is rendered into one mail and marked sent
#.  otherwise the event simply stays queued - the next event to arrive once the window has passed
    flushes it together with whatever else has accumulated since

The window is a fixed 15 minutes (`ImmediateEmailService::THROTTLE_WINDOW_SECONDS`), not
configurable, per the issue's "simple throttle" scope.

Template reuse
---------------

Deliberately reuses `DigestGroupBuilder` and `DigestMailFactory` unchanged rather than a second
copy of either: since the immediate channel only ever flushes rows for one single `(tablename,
record_uid)` at a time, `DigestGroupBuilder::build()` always returns exactly one
`DigestRecordGroup`, rendered through the very same `NotificationDigest` Fluid template the daily
digest uses - a batched immediate mail therefore looks identical in structure to a one-record
digest mail, just delivered per event instead of once a day.

Gating
------

Gated behind its own `notificationImmediateEmail` extension configuration toggle (mirroring
`notificationDigestEmail`), independent of the digest channel - an admin can disable either
channel without affecting the other. `ImmediateEmailChannel::supports()` additionally requires the
recipient to still exist (not deleted/disabled), have a valid email address, and have opted into
both User Settings toggles above.

..  _content-change-notifications:

Content change notifications
=============================

`ContentChangeNotificationService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/ContentChangeNotificationService.php>`__
(issue #309) closes the one gap MVP watching deliberately left open: the eye icon otherwise only
covers *planner* events (status/assignee/comment), not "someone edited this page's content" in
general. It is **opt-in and off by default** - gated behind its own `notificationContentChanged`
extension configuration toggle (`notifications` category), independent of every other
notification toggle.

Trigger and cheap early return
-------------------------------

Unlike every other event type, `content_changed` is dispatched directly from
`DataHandlerHook <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Hooks/DataHandlerHook.php>`__,
which runs on *every* record save - so the "no overhead for unwatched records" acceptance
criterion is a real performance concern here, not just a nice-to-have. The hook's own feature-flag
check is a plain in-memory read (no query), and `ContentChangeNotificationService` then checks
`WatcherService::getActiveWatchers()` - a single indexed query - *before* building any payload or
calling `NotificationDispatcher`. An unwatched record therefore costs one indexed `SELECT` and
nothing more: no title resolution, no dispatch, no write.

For a `tt_content` change, the parent page's watchers are notified too (per the issue's "edits to
content elements on the page count as a change to the watched page" rule). That needs the
element's page, so such a save costs up to three queries rather than one: the
`RecordRepository::findPidByUid()` lookup plus a watcher lookup for the element and for its page.
The page lookup is skipped entirely when `pages` is not a tracked table, and no other table pays
for it.

Changes to the content planner's own fields (status, assignee, comment count) and pure
housekeeping writes such as re-sorting are not treated as content changes. They already have
their own notifications, so counting them here would notify twice for one action.

Aggregation
-----------

Aggressive aggregation is the point - the issue's stated failure mode is noise, not silence.
Unlike every other `event_type`, `content_changed` rows are **not** appended one per occurrence;
`NotificationRepository::upsertContentChange()` finds this recipient's existing, not-yet-digested
`content_changed` row for the same `(tablename, record_uid)` created on the same calendar day and
merges the new occurrence's payload into it in place via `ContentChangePayloadMerger` - summing
`changeCount` and unioning `actorUids` - rather than inserting a new row. Any number of saves by
any number of actors against one record on one day therefore collapse into exactly one
notification per watcher, with a running counter, and appear in the backend toolbar dropdown as
that single collapsed entry.

The `digested_at IS NULL` clause in that lookup is deliberate and is the *entire* mechanism behind
"once digested, further changes must never revive the digested row": a row that has already been
mailed by the digest simply stops matching the aggregation query, so the next change after
digestion falls through to a plain insert and starts a fresh counter from one - no lost counters
(the digested row keeps whatever total it had when it was mailed), and no double-digesting (the
old row is never touched again).

The email digest renders the collapsed entry as a single line, e.g. "Content edited by 2 users,
14 changes" (`DigestGroupBuilder`/`DigestMailFactory`, shared with the toolbar's own rendering of
the same payload counters in `NotificationCenterDataProvider`) - summed/unioned again across
however many daily rows accumulated since the last digest run.

Workspaces: publish, not save
-------------------------------

This is the one deliberate difference from the "fires on save, not on publish" rule documented
below for every other event type. `DataHandlerHook` skips the live-save trigger entirely while
`$GLOBALS['BE_USER']->workspace !== 0` (i.e. the save produced a workspace draft, not a live
record) and instead fires from `processCmdmap_postProcess()` when it observes a `cmd[table][uid]
['version'] = ['action' => 'swap', ...]` command - the shape a workspace publish reaches
DataHandler as. `processCmdmap_postProcess` sees this regardless of which hook object actually
performs the swap (e.g. EXT:workspaces' own): DataHandler always runs every registered
`processCmdmap_postProcess` after handling a command, whether the command was handled by
DataHandler's own switch or fully intercepted by another hook's `processCmdmap()`.

The reasoning is specific to this event type and does not generalize back to the others: a
content editor may save a workspace draft many times before it is ever reviewed, let alone
published, and every one of those drafts is invisible to anyone outside the workspace. Notifying
watchers on every draft save would mean notifying them about content they cannot even see yet -
exactly the "noise is the failure mode" concern the issue calls out. `StatusChangeEvent`/
`AssigneeChangedEvent`/`CommentCreatedEvent` do not have this problem: a status/assignee/comment
change is meaningful review-workflow signal the moment it happens, draft or not, so those three
keep firing on save.

Mentions
========

..  note::

    Added in 3.2.0 for issue `#305 <https://github.com/xima-media/xima-typo3-content-planner/issues/305>`__.
    The CKEditor5 comment composer this feeds into (issue #327) landed in a separate branch
    stack and is not yet merged with the notifications stack this page documents - see the
    "Integration note" in `PR #305's description
    <https://github.com/xima-media/xima-typo3-content-planner/pull/305>`__ for the exact wiring
    step that remains once the two stacks meet.

Storage contract
-----------------

A mention is persisted **inline, inside the comment's existing `content` HTML field** - no
separate column or table. `MentionUtility <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Utility/Data/MentionUtility.php>`__
defines the marker:

..  code-block:: html

    <a class="ctp-mention" data-mention-uid="42">@display-name-at-mention-time</a>

The UID, not the display name, is the source of truth: `MentionUtility::renderContentWithMentionLinks()`
re-resolves the mentioned user's *current* display name and link target on every render rather
than trusting the stored text, so a later username/realName change is reflected automatically -
`CommentItem::getContent()` is what the comment partial now renders instead of the raw `content`
column directly.

Permission-filtered suggestion list
-------------------------------------

`MentionController::suggestAction() <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Controller/MentionController.php>`__
is the AJAX feed a CKEditor5 Mention plugin's async `feed` callback calls into. Per the issue's
own scope decision, the candidate pool is a *pragmatic approximation* - every content-planner-permitted
user (`BackendUserRepository::findAllWithPermission()`, the same pool
`RecordController::assigneeSelectionAction()` already offers for assignment) - rather than exact
per-record permission resolution (groups/mounts/page permissions), which would be too expensive to
evaluate on every keystroke of a live suggestion feed.

`MentionNotificationService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/MentionNotificationService.php>`__
applies the *same* filter again, defensively, before ever notifying or auto-watching a mentioned
uid extracted from persisted comment content - a hand-authored (or otherwise API-injected) mention
marker referencing a uid outside that pool is silently dropped, so the suggestion list is not just
a UI nicety but the actual authorization boundary.

Being permitted to use the content planner is not the same as being allowed to open the record the
comment sits on, though. Before notifying, `MentionNotificationService` therefore also asks
`RecipientAccessChecker` whether the mentioned user can still read that record, so a mention cannot
be used to hand someone a page title they have no access to. The number of mentions honoured per
comment is capped as well: a mention is the one path that bypasses the watcher gate, and combined
with the immediate e-mail channel an unbounded list would turn a single comment into a mail to
everyone.

..  note::
    **Not yet wired into the editor.** The backend side described here is complete, but nothing
    produces the `ctp-mention` markup through the UI: the CKEditor5 Mention plugin that would call
    `suggestAction()` lives in the comment composer (CP-28, #327), which is developed on a separate
    branch. Until the two are merged, mention markers can only arrive through the API, and the
    feature is not reachable for editors. The wiring point exists on that side:
    `ModifyCommentEditorConfigurationEvent` lets a listener add the plugin and its `feed` callback
    to the composer's CKEditor5 configuration without replacing the factory.

Dispatch: reaches its target even without a prior watch
-----------------------------------------------------------

Every other event type in this document is delivered exclusively to `WatcherService::getActiveWatchersWithSource()`'s
result - a mention could never reach anyone who has not already watched the record, which defeats
the point of a mention. `NotificationDispatcher::dispatchMention()` is a separate, direct-recipient
entry point that never queries watch state at all: it is handed the mentioned uid directly by
`MentionNotificationService` and always stamps `NotificationReason::Mentioned` regardless of any
watch relation that recipient may or may not separately have.

Mute-vs-mention
----------------

The decision the issue asked for: a mention **bypasses** a recipient's sticky `manual_unwatch` for
the one notification it produces, but does **not** re-subscribe them - a muted user still sees the
mention (toolbar dropdown/email, if enabled), just not any *other* future activity on that record
unless they explicitly re-watch it. Concretely, `MentionNotificationService::notifyMentions()`
always does both of the following for every notifiable mentioned uid, unconditionally:

#.  `WatcherService::watch($table, $uid, $mentionedUid, WatchSource::Mention)` - subject to the
    *same* sticky-against-manual rule every other auto-watch source already follows (see
    `WatcherServiceTest::mentionTriggerNeverReactivatesManualUnwatch()`): a prior `manual_unwatch`
    is left untouched, exactly as `Assignment`/`Comment`/`StatusChange` already behave.
#.  `NotificationDispatcher::dispatchMention()` - which, as above, never even asks whether the
    recipient is watching, muted or otherwise.

The combination is what produces the mute-vs-mention behavior: step 1 guarantees a muted user
never gets silently re-subscribed, while step 2 guarantees they still see this one, high-signal
notification. See `MentionNotificationServiceTest::deliversTheNotificationToAMutedUserButDoesNotReSubscribeThem()`
for the full-stack proof against a real database.

Cleanup
=======

`content-planner:notification:cleanup` (issue #304) is a second schedulable Symfony command,
intended to run alongside the email digest (recommended: daily, after the digest). It applies
four independent rules in one run:

-   delete **read** notifications older than `notificationRetentionReadDays` (default **30**)
-   delete **unread** notifications older than `notificationRetentionUnreadDays` (default **90**)
    - deliberately longer, since an unread notification has not yet been seen by its recipient
-   delete watcher/notification rows whose referenced record no longer exists (hard-deleted, or
    soft-deleted on a table with a TCA `deleted` column)
-   delete watcher/notification rows owned by a backend user that is now deleted or disabled

Both thresholds are read from this extension's extension configuration (`notifications` category),
not from a CLI option - this keeps the two commands' configuration in one place. Age is measured
from a notification's `crdate` (creation), independent of read/unread state; `read_at`/`digested_at`
only decide *which* rule a row falls under, not how "old" it is.

..  code-block:: bash

    vendor/bin/typo3 content-planner:notification:cleanup
    vendor/bin/typo3 content-planner:notification:cleanup --dry-run

`--dry-run` runs every rule's matching logic but only *counts* what it would have deleted, printed
per rule plus a grand total - nothing is deleted.

Orphan detection
-----------------

The two orphan rules are checked once per distinct reference, not once per notification/watcher
row: `NotificationRepository::findDistinctTableRecordPairs()` and
`WatcherRepository::findDistinctTableRecordPairs()` (`(tablename, record_uid)` pairs), and their
`findDistinctBackendUsers()` counterparts, are unioned and deduped before a single existence check
per distinct record (`RecordRepository::existingUids()`) or backend user
(`BackendUserRepository::activeUids()`). `existingUids()` deliberately ignores the hidden/start/end
time restrictions (a merely hidden or time-restricted record still "exists") and is **not** gated
by `ExtensionUtility::getRecordTables()` like `RecordRepository::findByUid()` is: a table can be
de-registered from content planner tracking while old rows for it still need cleaning up. A
backend user is "orphaned" the same way whether its row was soft-deleted (`deleted = 1`), disabled
(`disable = 1`), or is hard-deleted and simply absent altogether.

Chunked deletes
---------------

Every delete in this command - the two age-based rules and the two orphan rules - goes through the
same private `deleteMatchingInChunks()` engine in `NotificationRepository`/`WatcherRepository`:
repeatedly `SELECT` up to 500 matching uids and `DELETE ... WHERE uid IN (:uids)` exactly those,
until a batch returns fewer than 500 rows. Neither statement's cost scales with the total table
size, so the command stays safe to run against a notification table with millions of historic rows
- there is no single unbounded `DELETE` holding one long-running lock. `--dry-run` reuses the exact
same `$configureWhere` callback via a `COUNT` query instead of the delete loop, so the two modes can
never disagree about which rows match.

Recommended scheduler setup
----------------------------

Run both commands daily, digest before cleanup, e.g. as a crontab entry:

..  code-block:: text

    0 6 * * * vendor/bin/typo3 content-planner:notification:digest
    15 6 * * * vendor/bin/typo3 content-planner:notification:cleanup

or as two entries in the TYPO3 scheduler backend module (**Execute console commands** task),
using the same two command identifiers.

Workspaces
==========

`StatusChangeEvent`/`AssigneeChangedEvent`/`CommentCreatedEvent` are all dispatched from inside
`DataHandlerHook`, which runs on every save - including a save into a workspace, i.e. on
creating/editing a versioned draft record. None of them are gated on that draft later being
published. Notifications for planner events on versioned records therefore fire **on save, not
on publish**, and this remains the deliberate, permanent behavior for these three event types -
see :ref:`Content change notifications <content-change-notifications>` above for the one
exception (`content_changed`, issue #309), which fires on publish instead for reasons specific to
that event type.

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
    -   `NotificationCleanupCommand <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Command/NotificationCleanupCommand.php>`__
    -   `NotificationRetentionService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Retention/NotificationRetentionService.php>`__
    -   `ImmediateEmailChannel <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Channel/ImmediateEmailChannel.php>`__
    -   `ImmediateEmailService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/Immediate/ImmediateEmailService.php>`__
    -   `ImmediateEmailQueueRepository <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Domain/Repository/ImmediateEmailQueueRepository.php>`__
    -   `MentionUtility <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Utility/Data/MentionUtility.php>`__
    -   `MentionNotificationService <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Service/Notification/MentionNotificationService.php>`__
    -   `MentionController <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/Controller/MentionController.php>`__
    -   `NotifyOnMentionListener <https://github.com/xima-media/xima-typo3-content-planner/blob/main/Classes/EventListener/Notification/NotifyOnMentionListener.php>`__
