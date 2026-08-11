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

namespace Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function ctype_digit;
use function in_array;
use function intval;

/**
 * ContentPlannerFacetQuery.
 *
 * Deliberately has no dependency on konradmichalik/typo3-pagetree-facets: that
 * package is suggest-only (see Configuration::FEATURE_PAGETREE_FACETS_INTEGRATION
 * and the design spec), so this class stays testable on the existing v13/SQLite
 * suite without it installed. ContentPlannerFacet (the FacetInterface adapter)
 * translates the real Token/FilterContext types into the plain values this
 * class expects.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class ContentPlannerFacetQuery
{
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @param list<int> $requestedUids
     * @param list<int> $allowedUids   empty = no restriction (all allowed)
     *
     * @return list<int>
     */
    public function filterAllowedStatusUids(array $requestedUids, array $allowedUids): array
    {
        if ([] === $allowedUids) {
            return $requestedUids;
        }

        return array_values(array_intersect($requestedUids, $allowedUids));
    }

    /**
     * @param list<string> $values already-permission-filtered status uid strings, and/or "none"
     *
     * @return list<int> page uids
     */
    public function resolveByStatus(array $values, bool $includeContentElements): array
    {
        $matchNone = in_array('none', $values, true);
        $uids = array_values(array_filter(array_map(
            static fn (string $value): ?int => ctype_digit($value) ? (int) $value : null,
            $values,
        ), static fn (?int $value): bool => null !== $value));

        return $this->resolveFieldMatch(Configuration::FIELD_STATUS, $uids, $matchNone, $includeContentElements);
    }

    /**
     * @param list<string> $values "me", numeric BE user uid strings, and/or "none"
     *
     * @return list<int> page uids
     */
    public function resolveByAssignee(array $values, int $currentUserUid, bool $includeContentElements): array
    {
        $matchNone = in_array('none', $values, true);
        $uids = [];
        foreach ($values as $value) {
            if ('me' === $value) {
                $uids[] = $currentUserUid;
            } elseif (ctype_digit($value)) {
                $uids[] = (int) $value;
            }
        }

        return $this->resolveFieldMatch(Configuration::FIELD_ASSIGNEE, array_values(array_unique($uids)), $matchNone, $includeContentElements);
    }

    /**
     * @param list<string> $values subset of "open", "resolved", "todo", "mine", "none"
     *
     * @return list<int> page uids
     */
    public function resolveByComments(array $values, int $currentUserUid, bool $todoEnabled, bool $includeContentElements): array
    {
        $recognized = ['open', 'resolved', 'mine', 'none'];
        if ($todoEnabled) {
            $recognized[] = 'todo';
        }
        $values = array_values(array_intersect($values, $recognized));
        if ([] === $values) {
            return [];
        }

        $pageUidSets = [];

        if (in_array('none', $values, true)) {
            $pageUidSets[] = $this->resolveFieldMatch(Configuration::FIELD_COMMENTS, [0], false, $includeContentElements);
        }

        $commentStates = array_values(array_diff($values, ['none']));
        if ([] !== $commentStates) {
            $pageUidSets[] = $this->resolveCommentStates($commentStates, $currentUserUid, $includeContentElements);
        }

        return [] === $pageUidSets ? [] : array_values(array_unique(array_merge(...$pageUidSets)));
    }

    /**
     * @param list<string> $states subset of "open", "resolved", "todo", "mine"
     *
     * @return list<int> page uids
     */
    private function resolveCommentStates(array $states, int $currentUserUid, bool $includeContentElements): array
    {
        $tables = $this->pageHostedTables($includeContentElements);
        $pageUidSets = [];
        foreach ($tables as $table) {
            $foreignUids = $this->fetchCommentForeignUids($table, $states, $currentUserUid);
            if ([] === $foreignUids) {
                continue;
            }
            $pageUidSets[] = 'pages' === $table ? $this->nonDeletedPageUids($foreignUids) : $this->mapRecordUidsToPageUids($table, $foreignUids);
        }

        return [] === $pageUidSets ? [] : array_values(array_unique(array_merge(...$pageUidSets)));
    }

    /**
     * @param list<string> $states
     *
     * @return list<int> tx_ximatypo3contentplanner_comment.foreign_uid values
     */
    private function fetchCommentForeignUids(string $table, array $states, int $currentUserUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_COMMENT);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        $conditions = [];
        foreach ($states as $state) {
            $condition = match ($state) {
                'open' => $queryBuilder->expr()->eq('resolved_date', 0),
                'resolved' => $queryBuilder->expr()->gt('resolved_date', 0),
                'mine' => $queryBuilder->expr()->eq('author', $queryBuilder->createNamedParameter($currentUserUid, Connection::PARAM_INT)),
                'todo' => (string) $queryBuilder->expr()->and(
                    $queryBuilder->expr()->gt('todo_total', 0),
                    $queryBuilder->expr()->lt('todo_resolved', $queryBuilder->quoteIdentifier('todo_total')),
                ),
                default => null,
            };
            if (null !== $condition) {
                $conditions[] = $condition;
            }
        }
        if ([] === $conditions) {
            return [];
        }

        return array_map(intval(...), $queryBuilder
            ->select('foreign_uid')
            ->distinct()
            ->from(Configuration::TABLE_COMMENT)
            ->where(
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->or(...$conditions),
            )
            ->executeQuery()
            ->fetchFirstColumn());
    }

    /**
     * @param list<int> $uids
     *
     * @return list<int>
     */
    private function mapRecordUidsToPageUids(string $table, array $uids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        $pids = array_map(intval(...), $queryBuilder
            ->select('pid')
            ->distinct()
            ->from($table)
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, ArrayParameterType::INTEGER)))
            ->executeQuery()
            ->fetchFirstColumn());

        return $this->nonDeletedPageUids($pids);
    }

    /**
     * @param list<int> $intValues
     *
     * @return list<int> page uids
     */
    private function resolveFieldMatch(string $field, array $intValues, bool $matchNull, bool $includeContentElements): array
    {
        if ([] === $intValues && !$matchNull) {
            return [];
        }

        $tables = $this->pageHostedTables($includeContentElements);
        $pageUidSets = [];
        foreach ($tables as $table) {
            $pageUidSets[] = $this->fetchPageUidsForTable(
                $table,
                static function (QueryBuilder $queryBuilder) use ($field, $intValues, $matchNull): void {
                    $conditions = [];
                    if ([] !== $intValues) {
                        $conditions[] = $queryBuilder->expr()->in(
                            $field,
                            $queryBuilder->createNamedParameter($intValues, ArrayParameterType::INTEGER),
                        );
                    }
                    if ($matchNull) {
                        $conditions[] = $queryBuilder->expr()->or(
                            $queryBuilder->expr()->isNull($field),
                            $queryBuilder->expr()->eq($field, 0),
                        );
                    }
                    $queryBuilder->where($queryBuilder->expr()->or(...$conditions));
                },
            );
        }

        return array_values(array_unique(array_merge(...$pageUidSets)));
    }

    /**
     * Tables whose "pid" (or, for pages itself, "uid") meaningfully identifies
     * a page. sys_file_metadata and the folder status table are deliberately
     * excluded: their pid does not point at a page at all - folder status rows
     * are created with pid=0 (see FolderStatusRepository::create()) - so
     * including them here would resolve to a bogus "page 0" match instead of no
     * match. tt_content and any registerAdditionalRecordTables entry are
     * page-child records with a real pid, so they stay in.
     *
     * @return list<string>
     */
    private function pageHostedTables(bool $includeContentElements): array
    {
        if (!$includeContentElements) {
            return ['pages'];
        }

        return array_values(array_diff(
            ExtensionUtility::getRecordTables(),
            ['sys_file_metadata', Configuration::TABLE_FOLDER],
        ));
    }

    /**
     * Page uids matching $applyWhere in $table. For "pages" the matching uid IS
     * the page uid; for every other registered table (tt_content, ...) the
     * matching rows' pid is the page uid.
     *
     * @param callable(QueryBuilder): void $applyWhere
     *
     * @return list<int>
     */
    private function fetchPageUidsForTable(string $table, callable $applyWhere): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        $pageUidColumn = 'pages' === $table ? 'uid' : 'pid';
        $queryBuilder->select($pageUidColumn)->distinct()->from($table);
        $applyWhere($queryBuilder);

        $uids = array_map(intval(...), $queryBuilder->executeQuery()->fetchFirstColumn());

        // For "pages" the DeletedRestriction above already excludes deleted rows.
        // For every other table (tt_content, ...) it only excludes deleted CHILD
        // rows - a page that is itself deleted, but whose child row survived
        // without a cascading delete, would otherwise still resolve as a match.
        return 'pages' === $table ? $uids : $this->nonDeletedPageUids($uids);
    }

    /**
     * @param list<int> $pageUids
     *
     * @return list<int>
     */
    private function nonDeletedPageUids(array $pageUids): array
    {
        if ([] === $pageUids) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return array_map(intval(...), $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($pageUids, ArrayParameterType::INTEGER)))
            ->executeQuery()
            ->fetchFirstColumn());
    }
}
