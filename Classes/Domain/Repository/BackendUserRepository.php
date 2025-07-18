<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class BackendUserRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }
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
                        $queryBuilder->createNamedParameter('%,tx_ximatypo3contentplanner:content-status,%')
                    )
                )->__toString()
            )
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('be_users.admin', 1),
                    $queryBuilder->expr()->neq('be_users.deleted', 0),
                    $queryBuilder->expr()->neq('be_users.disable', 0),
                    $queryBuilder->expr()->isNotNull('bg.uid')
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
