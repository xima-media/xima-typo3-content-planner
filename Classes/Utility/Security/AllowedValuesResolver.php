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

namespace Xima\XimaTypo3ContentPlanner\Utility\Security;

use Doctrine\DBAL\{ArrayParameterType, Exception};
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * AllowedValuesResolver.
 *
 * Resolves allowed statuses/tables for the current backend user from their
 * user groups, with per-request memoization. Extracted from PermissionUtility
 * to keep its cognitive complexity in check.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class AllowedValuesResolver
{
    /**
     * Static cache for allowed values from user groups (per-request memoization).
     * Key: column name, Value: resolved allowed values array.
     *
     * @var array<string, array<int, mixed>>
     */
    private static array $allowedValuesCache = [];

    /**
     * Reset the static cache. Useful for testing or after permission changes.
     *
     * @internal
     */
    public static function resetCache(): void
    {
        self::$allowedValuesCache = [];
    }

    /**
     * Get the list of allowed status UIDs for the current user.
     *
     * @return array<int, int>
     *
     * @throws Exception
     */
    public static function getAllowedStatusUidsForUser(): array
    {
        return self::getAllowedValuesFromUserGroups(
            'tx_ximatypo3contentplanner_allowed_statuses',
            static fn (string $value): array => GeneralUtility::intExplode(',', $value, true),
        );
    }

    /**
     * Get the list of allowed tables for the current user.
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    public static function getAllowedTablesForUser(): array
    {
        return self::getAllowedValuesFromUserGroups(
            'tx_ximatypo3contentplanner_allowed_tables',
            static fn (string $value): array => GeneralUtility::trimExplode(',', $value, true),
        );
    }

    /**
     * Generic method to get allowed values from user groups.
     *
     * @param callable(string): array<int, mixed> $explodeFunc
     *
     * @return array<int, mixed>
     *
     * @throws Exception
     */
    private static function getAllowedValuesFromUserGroups(string $column, callable $explodeFunc): array
    {
        if (isset(self::$allowedValuesCache[$column])) {
            return self::$allowedValuesCache[$column];
        }

        $userGroupIds = GeneralUtility::intExplode(',', (string) (self::getBackendUser()->user['usergroup'] ?? ''), true);

        if ([] === $userGroupIds) {
            self::$allowedValuesCache[$column] = [];

            return self::$allowedValuesCache[$column];
        }

        $allowedValues = [];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('be_groups');

        $result = $queryBuilder
            ->select($column)
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($userGroupIds, ArrayParameterType::INTEGER),
                ),
            )
            ->executeQuery();

        while ($row = $result->fetchAssociative()) {
            $values = $explodeFunc((string) ($row[$column] ?? ''));
            $allowedValues = [...$allowedValues, ...$values];
        }

        self::$allowedValuesCache[$column] = array_unique($allowedValues);

        return self::$allowedValuesCache[$column];
    }

    /**
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    private static function getBackendUser(): object
    {
        return $GLOBALS['BE_USER'];
    }
}
