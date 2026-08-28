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
 * Status.
 *
 * Readonly value object hydrated by {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository}.
 * Part of the public PSR-14 event API (StatusChangeEvent, PrepareStatusSelectionEvent) - changing
 * its public contract is a breaking change for extension authors.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class Status
{
    public function __construct(
        private int $uid = 0,
        private string $title = '',
        private string $icon = '',
        private string $color = '',
        private bool $isDefault = false,
    ) {}

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getColoredIcon(): string
    {
        return $this->icon.'-'.$this->color;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }
}
