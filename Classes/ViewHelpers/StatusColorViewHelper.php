<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

/**
 * StatusColorViewHelper.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class StatusColorViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly StatusRepository $statusRepository) {}

    /**
    * @var bool
    */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'statusId',
            'integer',
            '',
            true
        );
        $this->registerArgument(
            'colorName',
            'boolean',
            '',
            false,
            true
        );
    }

    public function render(): string
    {
        $status = $this->statusRepository->findByUid($this->arguments['statusId']);

        if (!$status instanceof Status) {
            return '';
        }
        if ((bool)$this->arguments['colorName']) {
            return $status->getColor();
        }
        return Configuration\Colors::get($status->getColor());
    }
}
