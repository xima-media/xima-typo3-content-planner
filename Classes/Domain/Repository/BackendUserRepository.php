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

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};

use function in_array;

/**
 * BackendUserRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class BackendUserRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array<string, mixed>>|bool
     *
     * @throws Exception
     */
    public function findAll(): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>|bool
     *
     * @throws Exception
     */
    public function findAllWithPermission(): array|bool
    {
        // First, get all group UIDs that have the permission (including subgroups recursively)
        $authorizedGroupUids = $this->getGroupUidsWithPermission('tx_ximatypo3contentplanner:content-status');

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
    public function getUsernameByUid(?int $uid): string
    {
        if (!(bool) $uid) {
            return '';
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        $userRecord = $queryBuilder
            ->select('username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchAssociative();

        if ($userRecord) {
            $user = $userRecord['username'];
            if ((bool) $userRecord['realName']) {
                $user = $userRecord['realName'].' ('.$user.')';
            }

            return htmlspecialchars($user, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * Get all group UIDs that have a specific permission (recursively including subgroups).
     *
     * @param string $permission The permission to check (e.g., 'tx_ximatypo3contentplanner:content-status')
     *
     * @return array<int> Array of group UIDs
     *
     * @throws Exception
     */
    private function getGroupUidsWithPermission(string $permission): array
    {
        $allGroups = $this->fetchAllBackendGroups();
        $groupMap = $this->buildGroupMap($allGroups);
        $authorizedGroups = $this->findDirectlyAuthorizedGroups($groupMap, $permission);
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
                'subgroups' => ($group['subgroup'] ?? '') !== '' ? array_map('intval', explode(',', (string) $group['subgroup'])) : [],
            ];
        }

        return $groupMap;
    }

    /**
     * @param array<int, array{custom_options: string, subgroups: array<int>}> $groupMap
     *
     * @return array<int, true>
     */
    private function findDirectlyAuthorizedGroups(array $groupMap, string $permission): array
    {
        $authorizedGroups = [];
        foreach ($groupMap as $uid => $data) {
            if ($this->hasPermission($data['custom_options'], $permission)) {
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
     * Check if a custom_options string contains a specific permission.
     *
     * @param string $customOptions The custom_options field value
     * @param string $permission    The permission to check
     */
    private function hasPermission(string $customOptions, string $permission): bool
    {
        if ('' === $customOptions) {
            return false;
        }

        // custom_options is stored as comma-separated values
        $options = array_map('trim', explode(',', $customOptions));

        return in_array($permission, $options, true);
    }
}
