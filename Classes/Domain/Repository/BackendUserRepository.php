<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserRepository
{
    /**
    * @throws Exception
    */
    public function findAll(): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->executeQuery()->fetchAllAssociative();
    }

    /**
    * @throws Exception
    */
    public function findByUid(int $uid): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();
    }

    /**
    * @throws Exception
    */
    public function findByUsername(string $username): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

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
