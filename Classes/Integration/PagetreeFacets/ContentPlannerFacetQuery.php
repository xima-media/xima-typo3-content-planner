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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

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
final class ContentPlannerFacetQuery
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

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
     * @param list<int> $intValues
     *
     * @return list<int> page uids
     */
    private function resolveFieldMatch(string $field, array $intValues, bool $matchNull, bool $includeContentElements): array
    {
        if ([] === $intValues && !$matchNull) {
            return [];
        }

        $tables = $includeContentElements ? ExtensionUtility::getRecordTables() : ['pages'];
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
                        $conditions[] = $queryBuilder->expr()->isNull($field);
                    }
                    $queryBuilder->where($queryBuilder->expr()->or(...$conditions));
                },
            );
        }

        return array_values(array_unique(array_merge(...$pageUidSets)));
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

        return array_map(intval(...), $queryBuilder->executeQuery()->fetchFirstColumn());
    }
}
