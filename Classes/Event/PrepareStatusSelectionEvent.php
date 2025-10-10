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
 * PrepareStatusSelectionEvent.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class PrepareStatusSelectionEvent
{
    final public const NAME = 'xima_typo3_content_planner.status.prepare_selection';

    /**
     * @param array<string, mixed> $selection
     */
    public function __construct(
        protected string $table,
        protected ?int $uid,
        protected object $context,
        protected array $selection,
        protected ?Status $currentStatus,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSelection(): array
    {
        return $this->selection;
    }

    /**
     * @param array<string, mixed> $selection
     */
    public function setSelection(array $selection): void
    {
        $this->selection = $selection;
    }

    public function getCurrentStatus(): ?Status
    {
        return $this->currentStatus;
    }

    public function getContext(): object
    {
        return $this->context;
    }
}
