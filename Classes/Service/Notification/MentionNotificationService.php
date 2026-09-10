<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Utility\Data\MentionUtility;

use function array_slice;
use function is_array;

/**
 * MentionNotificationService.
 *
 * Drives issue #305's @-mentions from {@see \Xima\XimaTypo3ContentPlanner\EventListener\Notification\NotifyOnMentionListener}:
 * given a comment's persisted content, extracts its {@see MentionUtility} markers and, for every
 * mentioned user who survives {@see self::resolveNotifiableMentionedUids()}, both auto-watches
 * them ({@see WatchSource::Mention}) and delivers an immediate `mentioned` notification via
 * {@see NotificationDispatcher::dispatchMention()} - which, unlike every other event type,
 * bypasses the "must be an active watcher" gate entirely, since a mention has to reach its
 * target even if they never watched the record and even if they muted it
 * ({@see \Xima\XimaTypo3ContentPlanner\Domain\Model\WatchMode::ManualUnwatch}) - see
 * Documentation/DeveloperCorner/Notifications.rst's "Mentions" section for the full decision.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
readonly class MentionNotificationService
{
    /**
     * A mention is the one path that bypasses the watcher gate, so nothing else limits how
     * many people a single comment can notify. Combined with the immediate e-mail channel,
     * an unbounded list would turn one comment into a mail to everyone. Mentions beyond this
     * many are ignored rather than delivered.
     */
    private const MAX_MENTIONS_PER_COMMENT = 10;

    public function __construct(
        private WatcherService $watcherService,
        private NotificationDispatcher $notificationDispatcher,
        private NotificationPayloadFactory $payloadFactory,
        private BackendUserRepository $backendUserRepository,
        private RecordRepository $recordRepository,
        private RecipientAccessChecker $accessChecker,
    ) {}

    /**
     * @throws Exception
     */
    public function notifyMentions(string $table, int $recordUid, string $content, ?int $actorUid, int $commentUid): void
    {
        if (!$this->watcherService->isWatchable($table)) {
            return;
        }

        $mentionedUids = MentionUtility::extractMentionedUserUids($content);
        if ([] === $mentionedUids) {
            return;
        }

        $mentionedUids = array_slice($mentionedUids, 0, self::MAX_MENTIONS_PER_COMMENT);

        $notifiableUids = $this->resolveNotifiableMentionedUids($mentionedUids);
        if ([] === $notifiableUids) {
            return;
        }

        $record = $this->recordRepository->findByUid($table, $recordUid, true);
        if (!is_array($record)) {
            return;
        }

        $payload = $this->payloadFactory->forMention($table, $recordUid, $commentUid);

        foreach ($notifiableUids as $mentionedUid) {
            if ($mentionedUid === $actorUid) {
                continue;
            }

            // Being permitted to use the content planner is not the same as being allowed to
            // see this record. Without this, anyone could mention a colleague on a page that
            // colleague cannot open and hand them its title - by mail, at that.
            if (!$this->accessChecker->canAccess($mentionedUid, $table, $record)) {
                continue;
            }

            $this->watcherService->watch($table, $recordUid, $mentionedUid, WatchSource::Mention);
            $this->notificationDispatcher->dispatchMention($table, $recordUid, $actorUid, $mentionedUid, $payload);
        }
    }

    /**
     * Defense in depth against a hand-authored mention marker referencing a backend user who
     * would never appear in the permission-filtered suggestion list
     * ({@see \Xima\XimaTypo3ContentPlanner\Controller\MentionController}): only active (not
     * deleted/disabled) *and* content-planner-permitted users can actually trigger a
     * notification or auto-watch, regardless of what a client sent as comment content.
     *
     * @param list<int> $mentionedUids
     *
     * @return list<int>
     *
     * @throws Exception
     */
    private function resolveNotifiableMentionedUids(array $mentionedUids): array
    {
        $activeUids = $this->backendUserRepository->filterActiveUids($mentionedUids);
        if ([] === $activeUids) {
            return [];
        }

        $permittedUids = array_map(
            static fn (array $user): int => (int) $user['uid'],
            $this->backendUserRepository->findAllWithPermission(),
        );

        return array_values(array_intersect($activeUids, $permittedUids));
    }
}
