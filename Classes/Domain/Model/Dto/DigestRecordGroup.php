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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

/**
 * DigestRecordGroup.
 *
 * One dedup'd entry in the email digest (issue #302): every non-digested notification for a
 * single record, collapsed by {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestGroupBuilder}
 * into one line per event type rather than one line per notification. `$statusChain` and
 * `$assigneeChain` are already display-ready (consecutive duplicates removed), e.g.
 * `['Draft', 'Review', 'Approved']` regardless of how many of the underlying events actually
 * changed the status - `$eventCount` is where that total survives.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class DigestRecordGroup
{
    /**
     * @param list<string> $statusChain
     * @param list<string> $assigneeChain
     */
    public function __construct(
        private string $table,
        private int $recordUid,
        private string $title,
        private string $reason,
        private array $statusChain,
        private array $assigneeChain,
        private ?string $latestCommentExcerpt,
        private int $commentCount,
        private int $eventCount,
        private int $latestCrdate,
        private int $contentChangeCount,
        private int $contentChangeActorCount,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRecordUid(): int
    {
        return $this->recordUid;
    }

    /**
     * Title snapshot from the latest notification's payload - see
     * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationPayloadFactory}.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The recipient's {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationReason} value
     * of the latest notification in this group.
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return list<string>
     */
    public function getStatusChain(): array
    {
        return $this->statusChain;
    }

    /**
     * @return list<string>
     */
    public function getAssigneeChain(): array
    {
        return $this->assigneeChain;
    }

    public function getLatestCommentExcerpt(): ?string
    {
        return $this->latestCommentExcerpt;
    }

    public function getCommentCount(): int
    {
        return $this->commentCount;
    }

    /**
     * Total number of underlying notifications collapsed into this group - independent of how
     * many entries `$statusChain`/`$assigneeChain` ended up with after deduping.
     */
    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function getLatestCrdate(): int
    {
        return $this->latestCrdate;
    }

    /**
     * Total changes across every {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType::ContentChanged}
     * row collapsed into this group (issue #309) - zero when the record had none.
     */
    public function getContentChangeCount(): int
    {
        return $this->contentChangeCount;
    }

    /**
     * Distinct actors across every content-change row collapsed into this group - zero when the
     * record had none.
     */
    public function getContentChangeActorCount(): int
    {
        return $this->contentChangeActorCount;
    }
}
