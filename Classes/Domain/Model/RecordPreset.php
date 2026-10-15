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
 * RecordPreset.
 *
 * A quick-access filter of the records module. The value is the query parameter that applies it,
 * so the module's chips and the dashboard KPI tiles link to the same filtered list.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
enum RecordPreset: string
{
    case Assignee = 'assignee';
    case Todo = 'todo';
    case OpenComments = 'openComments';

    public function filterValue(int $backendUserId): int
    {
        return self::Assignee === $this ? $backendUserId : 1;
    }
}
