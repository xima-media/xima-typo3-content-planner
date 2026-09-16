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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model;

/**
 * Notification.
 *
 * Readonly value object handed to every {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationChannelInterface}
 * by {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationDispatcher}. Not yet
 * persisted at this point - a channel decides on its own whether/how to store or send it.
 *
 * The payload is a plain, defensive array (see {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationPayloadFactory}):
 * consumers (backend badge #301, email digest #302) must tolerate unknown or missing keys, since
 * the schema is versioned via the `version` key and may grow over time.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class Notification
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private int $recipientUid,
        private NotificationEventType $eventType,
        private string $table,
        private int $recordUid,
        private ?int $actorUid,
        private NotificationReason $reason,
        private array $payload,
        private int $crdate,
    ) {}

    public function getRecipientUid(): int
    {
        return $this->recipientUid;
    }

    public function getEventType(): NotificationEventType
    {
        return $this->eventType;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRecordUid(): int
    {
        return $this->recordUid;
    }

    public function getActorUid(): ?int
    {
        return $this->actorUid;
    }

    public function getReason(): NotificationReason
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCrdate(): int
    {
        return $this->crdate;
    }
}
