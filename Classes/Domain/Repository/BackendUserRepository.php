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

use Doctrine\DBAL\{ArrayParameterType, Exception};
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function in_array;

/**
 * BackendUserRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class BackendUserRepository
{
    /**
     * Request-level memoization of resolved display names, keyed by user uid.
     * The same author/assignee is typically rendered many times per request
     * (e.g. once per comment or list row).
     *
     * @var array<int, string>
     */
    private array $usernameCache = [];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function findAll(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('uid', 'username', 'realName')
            ->from('be_users')
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function findAllWithPermission(): array
    {
        // First, get all group UIDs that have the permission (including subgroups recursively)
        $authorizedGroupUids = $this->getGroupUidsWithAnyPermission([
            Configuration::PERMISSION_GROUP.':'.Configuration::PERMISSION_CONTENT_STATUS,
            Configuration::PERMISSION_GROUP.':'.Configuration::PERMISSION_VIEW_ONLY,
            Configuration::PERMISSION_GROUP.':'.Configuration::PERMISSION_FULL_ACCESS,
        ]);

        // Build query for users
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $query = $queryBuilder
            ->select('be_users.*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('be_users.deleted', 0),
                $queryBuilder->expr()->eq('be_users.disable', 0),
            )
            ->orderBy('be_users.username');

        // Add condition: admin users OR users that belong to authorized groups
        if ([] !== $authorizedGroupUids) {
            $orConditions = [
                $queryBuilder->expr()->eq('be_users.admin', 1),
            ];

            // Check if user's usergroup field contains any of the authorized group UIDs
            foreach ($authorizedGroupUids as $groupUid) {
                $orConditions[] = $queryBuilder->expr()->inSet('be_users.usergroup', (string) $groupUid);
            }

            $query->andWhere($queryBuilder->expr()->or(...$orConditions));
        } else {
            // Only admin users if no groups have the permission
            $query->andWhere($queryBuilder->expr()->eq('be_users.admin', 1));
        }

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<string, mixed>|bool
     *
     * @throws Exception
     */
    public function findByUid(int $uid): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchAssociative();
    }

    /**
     * @return array<string, mixed>|bool
     *
     * @throws Exception
     */
    public function findByUsername(string $username): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($username, Connection::PARAM_STR)),
            )
            ->executeQuery()->fetchAssociative();
    }

    /**
     * @ToDo: Check if there is a core function to get the username by uid
     *
     * @throws Exception
     */
    /**
     * Batched display names for a set of backend users, in a single query.
     *
     * Unlike getUsernameByUid() the result is deliberately **not** HTML-escaped: this
     * feeds JSON responses, where escaping is the consumer's job at render time and
     * pre-escaping would leak entities such as "&amp;" into the payload.
     *
     * @param int[] $uids
     *
     * @return array<int, string> display name keyed by user uid; unknown uids are absent
     *
     * @throws Exception
     */
    public function getDisplayNamesByUids(array $uids): array
    {
        $uids = array_values(array_filter(array_map(intval(...), $uids), static fn (int $uid): bool => $uid > 0));
        if ([] === $uids) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $rows = $queryBuilder
            ->select('uid', 'username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, ArrayParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $names = [];
        foreach ($rows as $row) {
            $name = (string) $row['username'];
            if ((bool) $row['realName']) {
                $name = $row['realName'].' ('.$name.')';
            }
            $names[(int) $row['uid']] = $name;
        }

        return $names;
    }

    public function getUsernameByUid(?int $uid): string
    {
        if (!(bool) $uid) {
            return '';
        }

        if (array_key_exists($uid, $this->usernameCache)) {
            return $this->usernameCache[$uid];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        $userRecord = $queryBuilder
            ->select('username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchAssociative();

        $user = '';
        if ($userRecord) {
            $user = $userRecord['username'];
            if ((bool) $userRecord['realName']) {
                $user = $userRecord['realName'].' ('.$user.')';
            }

            $user = htmlspecialchars((string) $user, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        }

        return $this->usernameCache[$uid] = $user;
    }

    /**
     * Get all group UIDs that have any of the given permissions (recursively including subgroups).
     *
     * @param array<string> $permissions The permissions to check
     *
     * @return array<int> Array of group UIDs
     *
     * @throws Exception
     */
    private function getGroupUidsWithAnyPermission(array $permissions): array
    {
        $allGroups = $this->fetchAllBackendGroups();
        $groupMap = $this->buildGroupMap($allGroups);
        $authorizedGroups = $this->findDirectlyAuthorizedGroupsByAny($groupMap, $permissions);
        $this->expandAuthorizationToParentGroups($groupMap, $authorizedGroups);

        return array_keys($authorizedGroups);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    private function fetchAllBackendGroups(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');

        return $queryBuilder
            ->select('uid', 'subgroup', 'custom_options')
            ->from('be_groups')
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array<int, array<string, mixed>> $allGroups
     *
     * @return array<int, array{custom_options: string, subgroups: array<int>}>
     */
    private function buildGroupMap(array $allGroups): array
    {
        $groupMap = [];
        foreach ($allGroups as $group) {
            $groupMap[(int) $group['uid']] = [
                'custom_options' => $group['custom_options'] ?? '',
                'subgroups' => ($group['subgroup'] ?? '') !== '' ? array_map(intval(...), explode(',', (string) $group['subgroup'])) : [],
            ];
        }

        return $groupMap;
    }

    /**
     * @param array<int, array{custom_options: string, subgroups: array<int>}> $groupMap
     * @param array<string>                                                    $permissions
     *
     * @return array<int, true>
     */
    private function findDirectlyAuthorizedGroupsByAny(array $groupMap, array $permissions): array
    {
        $authorizedGroups = [];
        foreach ($groupMap as $uid => $data) {
            if ($this->hasAnyPermission($data['custom_options'], $permissions)) {
                $authorizedGroups[$uid] = true;
            }
        }

        return $authorizedGroups;
    }

    /**
     * @param array<int, array{custom_options: string, subgroups: array<int>}> $groupMap
     * @param array<int, true>                                                 $authorizedGroups
     */
    private function expandAuthorizationToParentGroups(array $groupMap, array &$authorizedGroups): void
    {
        $changed = true;
        while ($changed) {
            $changed = $this->authorizeGroupsWithAuthorizedSubgroups($groupMap, $authorizedGroups);
        }
    }

    /**
     * @param array<int, array{custom_options: string, subgroups: array<int>}> $groupMap
     * @param array<int, true>                                                 $authorizedGroups
     */
    private function authorizeGroupsWithAuthorizedSubgroups(array $groupMap, array &$authorizedGroups): bool
    {
        $changed = false;
        foreach ($groupMap as $uid => $data) {
            if (isset($authorizedGroups[$uid]) || !$this->hasAuthorizedSubgroup($data['subgroups'], $authorizedGroups)) {
                continue;
            }

            $authorizedGroups[$uid] = true;
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param array<int>       $subgroups
     * @param array<int, true> $authorizedGroups
     */
    private function hasAuthorizedSubgroup(array $subgroups, array $authorizedGroups): bool
    {
        foreach ($subgroups as $subgroupUid) {
            if (isset($authorizedGroups[$subgroupUid])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a custom_options string contains any of the given permissions.
     *
     * @param string        $customOptions The custom_options field value
     * @param array<string> $permissions   The permissions to check
     */
    private function hasAnyPermission(string $customOptions, array $permissions): bool
    {
        if ('' === $customOptions) {
            return false;
        }

        // custom_options is stored as comma-separated values
        $options = array_map(trim(...), explode(',', $customOptions));

        foreach ($permissions as $permission) {
            if (in_array($permission, $options, true)) {
                return true;
            }
        }

        return false;
    }
}
