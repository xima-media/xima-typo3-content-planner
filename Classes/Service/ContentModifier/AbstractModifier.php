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

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};

/**
 * AbstractModifier.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
abstract class AbstractModifier
{
    public function __construct(
        protected readonly StatusRepository $statusRepository,
        protected readonly RecordRepository $recordRepository,
    ) {}
}
