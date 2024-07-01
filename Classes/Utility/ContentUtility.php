<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentUtility
{
    public static function getPage(int $pageId): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        $page = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();

        return $page;
    }

    public static function getPageComments(int $pageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3contentplanner_comment');

        $comments = $queryBuilder
            ->select('*')
            ->from('tx_ximatypo3contentplanner_comment')
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter('pages', \PDO::PARAM_STR))
            )
            ->executeQuery()->fetchAllAssociative();

        foreach ($comments as &$comment) {
            $comment['date'] = date('d.m.Y H:i', $comment['crdate']);
            $comment['user'] = self::getBackendUsernameById($comment['author']);
        }
        return $comments;
    }

    public static function getBackendUsernameById(?int $userId): string
    {
        if (!$userId) {
            return '';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        $userRecord = $queryBuilder
            ->select('username')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userId, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchOne();

        if ($userRecord) {
            return htmlspecialchars($userRecord, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }
}
