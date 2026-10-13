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

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use Xima\XimaTypo3ContentPlanner\Configuration;

use function is_array;
use function json_decode;

/**
 * AssignmentHistoryRepository.
 *
 * Reads when, and by whom, a record's current assignee was set, from the history the DataHandler
 * keeps in `sys_history`. The record modal shows it next to the assignee.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class AssignmentHistoryRepository
{
    /**
     * Only a handful of the newest assignment changes can matter: the one that set the current
     * assignee is the latest one, unless the assignee was changed by something that left no history.
     */
    private const MAX_ROWS = 25;

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array{timestamp: int, userUid: int}|null
     *
     * @throws Exception
     */
    public function findLatest(string $table, int $uid, int $assignee): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_history');
        $rows = $queryBuilder
            ->select('tstamp', 'userid', 'history_data')
            ->from('sys_history')
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('recuid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->like('history_data', $queryBuilder->createNamedParameter('%'.Configuration::FIELD_ASSIGNEE.'%')),
            )
            ->orderBy('tstamp', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults(self::MAX_ROWS)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $history = json_decode((string) $row['history_data'], true);
            $newAssignee = is_array($history) ? ($history['newRecord'][Configuration::FIELD_ASSIGNEE] ?? null) : null;
            if (null !== $newAssignee && (int) $newAssignee === $assignee) {
                return ['timestamp' => (int) $row['tstamp'], 'userUid' => (int) $row['userid']];
            }
        }

        return null;
    }
}
