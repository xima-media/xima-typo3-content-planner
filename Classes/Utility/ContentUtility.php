<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

class ContentUtility
{
    public static function getStatus(?int $statusId): ?Status
    {
        if (!$statusId) {
            return null;
        }
        $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        return $statusRepository->findByUid($statusId);
    }

    public static function getPage(int $pageId): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();
    }

    public static function getAssignedPages(): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        return $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('tx_ximatypo3contentplanner_assignee', $queryBuilder->createNamedParameter($GLOBALS['BE_USER']->user['uid'], \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchAllAssociative();
    }

    public static function getRecordsByFilter(?string $search = null, ?int $status = null, ?int $assignee = null, ?string $type = null, int $maxResults = 20): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        $additionalWhere = '';
        $additionalParams = [
            'limit' => $maxResults,
        ];
        if ($search) {
            $additionalWhere .= ' AND (title LIKE :search OR uid = :uid)';
            $additionalParams['search'] = '%' . $search . '%';
            $additionalParams['uid'] = $search;
        }
        if ($status) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_status = :status';
            $additionalParams['status'] = $status;
        }
        if ($assignee) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_assignee = :assignee';
            $additionalParams['assignee'] = $assignee;
        }
        $sqlArray = [];
        foreach (ExtensionUtility::getRecordTables() as $table) {
            if ($type && $type !== $table) {
                continue;
            }
            $sqlArray[] = '(SELECT "' . $table . '" as tablename, uid, title, tstamp, tx_ximatypo3contentplanner_status, tx_ximatypo3contentplanner_assignee, tx_ximatypo3contentplanner_comments FROM ' . $table . ' WHERE tx_ximatypo3contentplanner_status IS NOT NULL' . $additionalWhere . ')';
        }
        $sql = implode(' UNION ', $sqlArray) . ' ORDER BY tstamp DESC LIMIT :limit';

        $statement = $queryBuilder->getConnection()->executeQuery($sql, $additionalParams);
        return $statement->fetchAllAssociative();
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

    public static function getComment(int $id): array|bool
    {
        if (!$id) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3contentplanner_comment');

        return $queryBuilder
            ->select('*')
            ->from('tx_ximatypo3contentplanner_comment')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();
    }

    public static function getBackendUserById(?int $userId): array|bool
    {
        if (!$userId) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        return $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userId, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();
    }

    public static function getBackendUsernameById(?int $userId): string
    {
        if (!$userId) {
            return '';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        $userRecord = $queryBuilder
            ->select('username', 'realName')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userId, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();

        if ($userRecord) {
            $user = $userRecord['username'];
            if ($userRecord['realName']) {
                $user = $userRecord['realName'] . ' (' . $user . ')';
            }
            return htmlspecialchars($user, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    public static function getBackendUsers(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        $query = $queryBuilder
            ->select('uid', 'username')
            ->from('be_users')
            ->orderBy('username', 'ASC');

        return $query->executeQuery()
            ->fetchAllAssociative();
    }

    public static function getExtensionRecords(string $table, ?int $pid = null): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $query = $queryBuilder
            ->select('uid', 'title', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->isNotNull('tx_ximatypo3contentplanner_status')
            )
            ->orderBy('tstamp', 'DESC');

        if ($pid) {
            $query->andWhere(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
            );
        }

        return $query->executeQuery()
            ->fetchAllAssociative();
    }

    public static function getExtensionRecord(string $table, int $uid): array|bool
    {
        if (!$table && !$uid) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $query = $queryBuilder
            ->select('*')
            ->from($table)->andWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
            );

        return $query->executeQuery()
            ->fetchAssociative();
    }
}
