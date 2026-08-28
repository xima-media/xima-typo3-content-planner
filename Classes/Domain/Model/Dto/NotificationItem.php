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
 * NotificationItem.
 *
 * Read-model for a single entry in the backend toolbar notification dropdown (issue #301).
 * Assembled by {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider}
 * from a raw `tx_ximatypo3contentplanner_notification` row: title and link are already
 * permission-checked against the viewing backend user by the time this object exists, so Fluid
 * templates and the toolbar item can render every field verbatim without a second access check.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class NotificationItem
{
    public function __construct(
        private int $uid,
        private string $eventType,
        private string $iconIdentifier,
        private string $title,
        private ?string $url,
        private string $actorLabel,
        private string $reasonLabel,
        private string $timeAgo,
        private bool $read,
        private ?string $changeSummary = null,
    ) {}

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getIconIdentifier(): string
    {
        return $this->iconIdentifier;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Null when the record could no longer be resolved, or the current backend user lacks
     * access to it - the dropdown then renders the entry without a link.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getActorLabel(): string
    {
        return $this->actorLabel;
    }

    public function getReasonLabel(): string
    {
        return $this->reasonLabel;
    }

    public function getTimeAgo(): string
    {
        return $this->timeAgo;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    /**
     * Non-null only for {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType::ContentChanged}
     * (issue #309), e.g. "Content edited by 2 users, 14 changes" - the toolbar's rendering of that
     * event type's aggregated payload counters.
     */
    public function getChangeSummary(): ?string
    {
        return $this->changeSummary;
    }
}
