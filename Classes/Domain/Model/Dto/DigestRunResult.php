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
 * DigestRunResult.
 *
 * Outcome of {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestService::processRecipient()}
 * for one recipient (issue #302) - what `content-planner:notification:digest` reports per
 * recipient, both for its `--dry-run` summary and its real run.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class DigestRunResult
{
    private function __construct(
        private int $backendUserUid,
        private string $status,
        private int $notificationCount,
        private int $groupCount,
        private ?string $skipReason,
    ) {}

    public static function sent(int $backendUserUid, int $notificationCount, int $groupCount): self
    {
        return new self($backendUserUid, 'sent', $notificationCount, $groupCount, null);
    }

    public static function wouldSend(int $backendUserUid, int $notificationCount, int $groupCount): self
    {
        return new self($backendUserUid, 'would-send', $notificationCount, $groupCount, null);
    }

    public static function skipped(int $backendUserUid, string $reason): self
    {
        return new self($backendUserUid, 'skipped', 0, 0, $reason);
    }

    public function getBackendUserUid(): int
    {
        return $this->backendUserUid;
    }

    public function wasSent(): bool
    {
        return 'sent' === $this->status;
    }

    public function wasSkipped(): bool
    {
        return 'skipped' === $this->status;
    }

    public function getNotificationCount(): int
    {
        return $this->notificationCount;
    }

    public function getGroupCount(): int
    {
        return $this->groupCount;
    }

    public function getSkipReason(): ?string
    {
        return $this->skipReason;
    }
}
