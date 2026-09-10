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

use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Event\{AssigneeChangedEvent, CommentCreatedEvent, StatusChangeEvent};

use function is_array;
use function is_string;

/**
 * NotificationPayloadFactory.
 *
 * Builds the `payload` array stored on a {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\Notification}.
 * Every payload carries a `version` key and a title snapshot (see {@see RecordTitleResolver}) so
 * it stays defensively renderable by future channels even after the schema grows or the
 * underlying record is renamed/deleted - see issue #300's acceptance criteria.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationPayloadFactory
{
    private const VERSION = 1;
    private const COMMENT_EXCERPT_LENGTH = 140;

    public function __construct(
        private readonly RecordTitleResolver $recordTitleResolver,
        private readonly CommentRepository $commentRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forStatusChange(StatusChangeEvent $event): array
    {
        return [
            ...$this->buildBasePayload($event->getTable(), $event->getUid()),
            'previousStatus' => $event->getPreviousStatus()?->getTitle(),
            'newStatus' => $event->getNewStatus()?->getTitle(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAssigneeChanged(AssigneeChangedEvent $event): array
    {
        return [
            ...$this->buildBasePayload($event->getTable(), $event->getUid()),
            'previousAssignee' => $event->getPreviousAssignee(),
            'newAssignee' => $event->getNewAssignee(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCommentCreated(CommentCreatedEvent $event): array
    {
        return [
            ...$this->buildBasePayload($event->getTable(), $event->getRecordUid()),
            'commentExcerpt' => $this->buildCommentExcerpt($event->getCommentUid()),
        ];
    }

    /**
     * Seed payload for a single content-change occurrence (issue #309): `changeCount` and
     * `actorUids` start at "one change by this actor" and are merged/summed in place by
     * {@see ContentChangePayloadMerger} as further changes to the same record accumulate on the
     * same day - see {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository::upsertContentChange()}.
     *
     * @return array<string, mixed>
     */
    public function forContentChanged(string $table, int $uid, ?int $actorUid): array
    {
        return [
            ...$this->buildBasePayload($table, $uid),
            'changeCount' => 1,
            'actorUids' => null !== $actorUid ? [$actorUid] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasePayload(string $table, int $uid): array
    {
        return [
            'version' => self::VERSION,
            'title' => $this->recordTitleResolver->resolve($table, $uid),
        ];
    }

    private function buildCommentExcerpt(int $commentUid): string
    {
        $comment = $this->commentRepository->findByUid($commentUid);
        if (!is_array($comment) || !is_string($comment['content'] ?? null)) {
            return '';
        }

        $content = strip_tags($comment['content']);
        if (mb_strlen($content) <= self::COMMENT_EXCERPT_LENGTH) {
            return $content;
        }

        return mb_substr($content, 0, self::COMMENT_EXCERPT_LENGTH).'...';
    }
}
