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

namespace Xima\XimaTypo3ContentPlanner\EventListener\Notification;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;
use Xima\XimaTypo3ContentPlanner\Service\Notification\MentionNotificationService;

use function is_array;
use function is_string;

/**
 * NotifyOnMentionListener.
 *
 * Applies to both root comments and replies, same as
 * {@see \Xima\XimaTypo3ContentPlanner\EventListener\Watcher\AutoWatchOnCommentCreatedListener}.
 * Deliberately scoped to comment *creation* only (issue #305) - detecting newly-added mentions
 * on an *edit* would require diffing old vs. new content to avoid re-notifying an already
 * mentioned user on every subsequent edit, which is out of scope for this issue.
 *
 * {@see CommentCreatedEvent} carries no content, so this listener re-reads the now-persisted
 * comment row itself, the same way {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationPayloadFactory::forCommentCreated()}
 * does for its comment excerpt.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(
    identifier: 'xima-typo3-content-planner/notification/notify-on-mention',
    after: 'xima-typo3-content-planner/watcher/auto-watch-on-comment-created',
)]
final readonly class NotifyOnMentionListener
{
    public function __construct(
        private MentionNotificationService $mentionNotificationService,
        private CommentRepository $commentRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(CommentCreatedEvent $event): void
    {
        $comment = $this->commentRepository->findByUid($event->getCommentUid());
        if (!is_array($comment) || !is_string($comment['content'] ?? null) || '' === $comment['content']) {
            return;
        }

        $this->mentionNotificationService->notifyMentions(
            $event->getTable(),
            $event->getRecordUid(),
            $comment['content'],
            $event->getAuthorUid(),
            $event->getCommentUid(),
        );
    }
}
