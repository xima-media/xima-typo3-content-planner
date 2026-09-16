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
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Throwable;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * NotificationDispatcher.
 *
 * Central dispatch point for the notification feature (issue #300): resolves the record's active
 * watchers, excludes the actor, derives a {@see NotificationReason} per recipient from their
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource}, and hands one {@see Notification}
 * per recipient to every registered {@see NotificationChannelInterface} that supports it.
 *
 * Deliberately thin: payload construction lives in {@see NotificationPayloadFactory}, reason
 * derivation on {@see NotificationReason} itself, and record-scoped event listeners
 * (Classes/EventListener/Notification/) are responsible for calling {@see self::dispatch()} with
 * the right {@see NotificationEventType} and pre-built payload.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationDispatcher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        private readonly WatcherService $watcherService,
        private readonly NotificationSuppressionState $suppressionState,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly iterable $channels,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @throws Exception
     */
    public function dispatch(
        NotificationEventType $eventType,
        string $table,
        int $uid,
        ?int $actorUid,
        array $payload,
    ): void {
        if ($this->suppressionState->isPaused() || !$this->watcherService->isWatchable($table)) {
            return;
        }

        $watchers = $this->watcherService->getActiveWatchersWithSource($table, $uid);
        if ([] === $watchers) {
            return;
        }

        // A watcher row outlives the account that created it, so without this a deleted or
        // disabled user keeps accumulating notifications - and, once the e-mail channels
        // land, keeps being written to.
        $activeRecipients = array_flip($this->backendUserRepository->filterActiveUids(array_keys($watchers)));

        $crdate = time();
        foreach ($watchers as $recipientUid => $source) {
            if ($recipientUid === $actorUid || !isset($activeRecipients[$recipientUid])) {
                continue;
            }

            $notification = new Notification(
                $recipientUid,
                $eventType,
                $table,
                $uid,
                $actorUid,
                NotificationReason::fromWatchSource($source),
                $payload,
                $crdate,
            );

            foreach ($this->channels as $channel) {
                if (!$channel->supports($notification)) {
                    continue;
                }

                try {
                    $channel->deliver($notification);
                } catch (Throwable $exception) {
                    // Dispatching happens inside the save request. A channel that reaches
                    // out to the network - the immediate e-mail channel is the first one
                    // that does - must not be able to fail the editor's save, or leave the
                    // remaining recipients unnotified because one delivery threw.
                    $this->logger?->error('Content planner notification channel failed', [
                        'channel' => $channel::class,
                        'recipient' => $recipientUid,
                        'table' => $table,
                        'record' => $uid,
                        'exception' => $exception,
                    ]);
                }
            }
        }
    }

    /**
     * Direct-recipient dispatch for @-mention notifications (issue #305). Unlike
     * {@see self::dispatch()}, this deliberately never consults
     * {@see WatcherService::getActiveWatchersWithSource()} (or any other watch-state query) at
     * all: a mention must reach its target even if they are not currently watching the record,
     * and even if they have a sticky {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\WatchMode::ManualUnwatch}
     * - see the "mute-vs-mention" decision in Documentation/DeveloperCorner/Notifications.rst.
     * The reason is always {@see NotificationReason::Mentioned}, since it describes *why this
     * notification exists*, not any watch relation the recipient may or may not separately have.
     *
     * Auto-watching the mentioned user (also subject to that same sticky rule) is a *separate*
     * concern, handled by the caller via {@see WatcherService::watch()} - this method only ever
     * delivers the one notification.
     *
     * @param array<string, mixed> $payload
     *
     * @throws Exception
     */
    public function dispatchMention(
        string $table,
        int $uid,
        ?int $actorUid,
        int $recipientUid,
        array $payload,
    ): void {
        if ($this->suppressionState->isPaused() || !$this->watcherService->isWatchable($table) || $recipientUid === $actorUid) {
            return;
        }

        $notification = new Notification(
            $recipientUid,
            NotificationEventType::Mentioned,
            $table,
            $uid,
            $actorUid,
            NotificationReason::Mentioned,
            $payload,
            time(),
        );

        foreach ($this->channels as $channel) {
            if ($channel->supports($notification)) {
                $channel->deliver($notification);
            }
        }
    }
}
