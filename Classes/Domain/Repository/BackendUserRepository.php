<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * BackendUserRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class BackendUserRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}
    /**
    * @return array<int, array<string, mixed>>|bool
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
                $queryBuilder->expr()->eq('be_users.disable', 0)
            )
            ->orderBy('be_users.username');

        // Add condition: admin users OR users that belong to authorized groups
        if ($authorizedGroupUids !== []) {
            $orConditions = [
                $queryBuilder->expr()->eq('be_users.admin', 1),
            ];

            // Check if user's usergroup field contains any of the authorized group UIDs
            foreach ($authorizedGroupUids as $groupUid) {
                $orConditions[] = $queryBuilder->expr()->inSet('be_users.usergroup', (string)$groupUid);
            }

            $query->andWhere($queryBuilder->expr()->or(...$orConditions));
        } else {
            // Only admin users if no groups have the permission
            $query->andWhere($queryBuilder->expr()->eq('be_users.admin', 1));
        }

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
    * Get all group UIDs that have a specific permission (recursively including subgroups).
    *
    * @param string $permission The permission to check (e.g., 'tx_ximatypo3contentplanner:content-status')
    * @return array<int> Array of group UIDs
    * @throws Exception
    */
    private function getGroupUidsWithPermission(string $permission): array
    {
        // Get all groups with their permissions and subgroups
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $allGroups = $queryBuilder
            ->select('uid', 'subgroup', 'custom_options')
            ->from('be_groups')
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()
            ->fetchAllAssociative();

        // Build a map for quick lookup
        $groupMap = [];
        foreach ($allGroups as $group) {
            $groupMap[(int)$group['uid']] = [
                'custom_options' => $group['custom_options'] ?? '',
                'subgroups' => ($group['subgroup'] ?? '') !== '' ? array_map('intval', explode(',', (string)$group['subgroup'])) : [],
            ];
        }

        // Find all groups that directly have the permission
        $authorizedGroups = [];
        foreach ($groupMap as $uid => $data) {
            if ($this->hasPermission($data['custom_options'], $permission)) {
                $authorizedGroups[$uid] = true;
            }
        }

        // Recursively find parent groups that include authorized subgroups
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($groupMap as $uid => $data) {
                // Skip if already authorized
                if (isset($authorizedGroups[$uid])) {
                    continue;
                }

                // Check if any subgroup is authorized
                foreach ($data['subgroups'] as $subgroupUid) {
                    if (isset($authorizedGroups[$subgroupUid])) {
                        $authorizedGroups[$uid] = true;
                        $changed = true;
                        break;
                    }
                }
            }
        }

        return array_keys($authorizedGroups);
    }

    /**
    * Check if a custom_options string contains a specific permission.
    *
    * @param string $customOptions The custom_options field value
    * @param string $permission The permission to check
    * @return bool
    */
    private function hasPermission(string $customOptions, string $permission): bool
    {
        if ($customOptions === '') {
            return false;
        }

        // custom_options is stored as comma-separated values
        $options = array_map('trim', explode(',', $customOptions));
        return in_array($permission, $options, true);
    }

    /**
    * @return array<string, mixed>|bool
    * @throws Exception
    */
    public function findByUid(int $uid): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();
    }

    /**
    * @return array<string, mixed>|bool
    * @throws Exception
    */
    public function findByUsername(string $username): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($username, Connection::PARAM_STR))
            )
            ->executeQuery()->fetchAssociative();
    }

    /**
    * @ToDo: Check if there is a core function to get the username by uid
    * @throws Exception
    */
    public function getUsernameByUid(?int $uid): string
    {
        if (!(bool)$uid) {
            return '';
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');

        $userRecord = $queryBuilder
            ->select('username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();

        if ($userRecord) {
            $user = $userRecord['username'];
            if ((bool)$userRecord['realName']) {
                $user = $userRecord['realName'] . ' (' . $user . ')';
            }
            return htmlspecialchars($user, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }
}
