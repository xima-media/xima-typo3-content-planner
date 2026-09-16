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

namespace Xima\XimaTypo3ContentPlanner\Utility\Data;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * RecordRestrictionUtility.
 *
 * Content Planner data always reflects the live record, regardless of the current
 * backend user's workspace, so workspace-version rows are excluded from every read.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordRestrictionUtility
{
    /**
     * Check if a table has the deleted field restriction.
     * Tables like sys_file_metadata don't have a deleted field.
     */
    public static function hasDeletedRestriction(string $table): bool
    {
        return isset($GLOBALS['TCA'][$table]['ctrl']['delete']);
    }

    public static function hasWorkspaceRestriction(string $table): bool
    {
        return (bool) ($GLOBALS['TCA'][$table]['ctrl']['versioningWS'] ?? false);
    }

    public static function applyWorkspaceRestriction(QueryBuilder $queryBuilder, string $table): void
    {
        if (self::hasWorkspaceRestriction($table)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('t3ver_wsid', 0));
        }
    }

    /**
     * Deleted/workspace restriction for QueryBuilder-based reads, mirroring buildLiveRestrictionSql().
     */
    public static function applyLiveRestrictions(QueryBuilder $queryBuilder, string $table): void
    {
        if (self::hasDeletedRestriction($table)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }

        self::applyWorkspaceRestriction($queryBuilder, $table);
    }

    /**
     * Deleted/workspace SQL fragment for raw UNION queries, e.g. " AND deleted = 0 AND t3ver_wsid = 0".
     * Empty per condition when the table doesn't have that restriction.
     */
    public static function buildLiveRestrictionSql(string $table, string $alias = ''): string
    {
        $prefix = '' !== $alias ? $alias.'.' : '';
        $sql = '';

        if (self::hasDeletedRestriction($table)) {
            $sql .= ' AND '.$prefix.'deleted = 0';
        }

        if (self::hasWorkspaceRestriction($table)) {
            $sql .= ' AND '.$prefix.'t3ver_wsid = 0';
        }

        return $sql;
    }
}
