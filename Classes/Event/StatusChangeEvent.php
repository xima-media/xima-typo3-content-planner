<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Event;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

/**
 * StatusChangeEvent.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusChangeEvent
{
    final public const NAME = 'xima_typo3_content_planner.status.change';

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function __construct(
        protected string $table,
        protected int $uid,
        protected array $fieldArray,
        protected ?Status $previousStatus,
        protected ?Status $newStatus,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFieldArray(): array
    {
        return $this->fieldArray;
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function setFieldArray(array $fieldArray): void
    {
        $this->fieldArray = $fieldArray;
    }

    public function getPreviousStatus(): ?Status
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): ?Status
    {
        return $this->newStatus;
    }

    public function setNewStatus(?Status $newStatus): void
    {
        $this->newStatus = $newStatus;
    }
}
