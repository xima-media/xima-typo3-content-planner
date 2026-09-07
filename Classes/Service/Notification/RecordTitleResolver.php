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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * RecordTitleResolver.
 *
 * Resolves a record's current title for the title snapshot stored in a notification's payload
 * (see {@see NotificationPayloadFactory}). Resolving it eagerly at dispatch time - rather than
 * lazily when a notification is rendered - is what keeps notifications renderable after the
 * record is later renamed or deleted, per issue #300's acceptance criteria.
 *
 * Uses {@see BackendUtility::getRecord()} rather than
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository::findByUid()}: the
 * latter selects the title field under a generic `"title"` alias for its own callers, which
 * {@see BackendUtility::getRecordTitle()} cannot resolve back to the table's actual TCA label
 * column.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordTitleResolver
{
    public function resolve(string $table, int $uid): string
    {
        $row = BackendUtility::getRecord($table, $uid);
        if (null === $row) {
            return BackendUtility::getNoRecordTitle();
        }

        return BackendUtility::getRecordTitle($table, $row);
    }
}
