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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        return $queryBuilder->select('be_users.*')
            ->from('be_users')
            ->leftJoin(
                'be_users',
                'be_groups',
                'bg',
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->neq('be_users.usergroup', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->like(
                        'bg.custom_options',
                        $queryBuilder->createNamedParameter('%tx_ximatypo3contentplanner:content-status%')
                    )
                )->__toString()
            )
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('be_users.deleted', 0),
                    $queryBuilder->expr()->eq('be_users.disable', 0),
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq('be_users.admin', 1),
                        $queryBuilder->expr()->isNotNull('bg.uid'),
                    ),
                )
            )
            ->orderBy('be_users.username')
            ->executeQuery()
            ->fetchAllAssociative();
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
