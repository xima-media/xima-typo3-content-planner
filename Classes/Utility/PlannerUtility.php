<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

class PlannerUtility
{
    /**
    * Simple function to get a list of all available status.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getListOfStatus();
    *
    * @return array
    * @throws \Doctrine\DBAL\Exception
    */
    public static function getListOfStatus(): array
    {
        return GeneralUtility::makeInstance(StatusRepository::class)->findAll();
    }

    /**
    * Simple function to update the status of a record.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::updateStatusForRecord('pages', 1, 'In Progress', 'admin');
    *
    * @param string $table
    * @param int $uid
    * @param \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|int|string $status
    * @param \Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser|int|string|null $assignee
    * @throws \Doctrine\DBAL\Exception
    */
    public static function updateStatusForRecord(string $table, int $uid, Status|int|string $status, BackendUser|int|string|null $assignee = null): void
    {
        self::preCheckRecordTable($table, $uid);

        $statusId = $status;
        if ($status instanceof Status) {
            $statusId = $status->getUid();
        } elseif (is_string($status)) {
            $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
            $statusId = $statusRepository->findByTitle($status)->getUid();
        }

        if (!$statusId) {
            throw new \InvalidArgumentException('Status "' . $statusId . '" is not a valid content planner status.', 9220772840);
        }

        $assigneeId = $assignee;
        if ($assignee instanceof BackendUser) {
            $assigneeId = $assignee->getUid();
        } elseif (is_string($assignee)) {
            $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
            $assigneeId = $backendUserRepository->findByUsername($assignee);
            if ($assigneeId) {
                $assigneeId = $assigneeId['uid'];
            }
        }

        GeneralUtility::makeInstance(RecordRepository::class)->updateStatusByUid($table, $uid, $statusId, $assigneeId);
    }

    /**
    * Simple function to get the status of a record.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getStatusOfRecord('pages', 1);
    *
    * @param string $table
    * @param int $uid
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    * @throws \Doctrine\DBAL\Exception
    */
    public static function getStatusOfRecord(string $table, int $uid): ?Status
    {
        $record = self::preCheckRecordTable($table, $uid);

        return GeneralUtility::makeInstance(StatusRepository::class)->findByUid($record['tx_ximatypo3contentplanner_status']);
    }

    /**
    * Simple function to get a status.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getStatus('Needs review');
    *
    * @param int|string $identifier
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public static function getStatus(int|string $identifier): ?Status
    {
        $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        if (is_string($identifier)) {
            return $statusRepository->findByTitle($identifier);
        }
        return $statusRepository->findByUid($identifier);
    }

    /**
    * Simple function to fetch all comments of a record.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getCommentsOfRecord('pages', 1);
    *
    * @param string $table
    * @param int $uid
    * @param bool $raw
    * @return array
    * @throws \Doctrine\DBAL\Exception
    */
    public static function getCommentsOfRecord(string $table, int $uid, bool $raw = false): array
    {
        self::preCheckRecordTable($table, $uid);
        return GeneralUtility::makeInstance(CommentRepository::class)->findAllByRecord($uid, $table, $raw);
    }

    /**
    * Simple function to add comment(s) to a content planner record.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::addCommentsToRecord('pages', 1, 'New Comment', 'admin');
    *
    * @param string $table
    * @param int $uid
    * @param array<int,string>|string $comments
    * @param BackendUser|int|string $author
    * @throws \Doctrine\DBAL\Exception
    */
    public static function addCommentsToRecord(string $table, int $uid, array|string $comments, BackendUser|int|string|null $author = null): void
    {
        $record = self::preCheckRecordTable($table, $uid);

        $authorId = $author;
        if ($author instanceof BackendUser) {
            $authorId = $author->getUid();
        } elseif (is_string($author)) {
            $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
            $authorId = $backendUserRepository->findByUsername($author)['uid'];
        }

        if (!$authorId) {
            throw new \InvalidArgumentException('Author "' . $authorId . '" is not a valid backend user.', 4723563571);
        }

        if (!is_array($comments)) {
            $comments = [$comments];
        }

        $pid = $table === 'pages' ? $record['uid'] : $record['pid'];
        $newIds = [];

        foreach ($comments as $comment) {
            $newId = StringUtility::getUniqueId('NEW');
            $data['tx_ximatypo3contentplanner_comment'][$newId] = [
                'foreign_uid' => $uid,
                'foreign_table' => $table,
                'content' => $comment,
                'pid' => $pid,
                'author' => $authorId,
            ];
            $newIds[] = $newId;
        }
        $data[$table][$uid]['tx_ximatypo3contentplanner_comments'] = count($newIds);

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
    }

    /**
    * Simple function to clear all comment(s) of a content planner record.
    * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::clearCommentsOfRecord('pages', 1);
    *
    * @param string $table
    * @param int $uid
    * @param string|null $like
    * @throws \Doctrine\DBAL\Exception
    */
    public static function clearCommentsOfRecord(string $table, int $uid, ?string $like = null): void
    {
        self::preCheckRecordTable($table, $uid);

        $commentsRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $commentsRepository->deleteAllCommentsByRecord($uid, $table, $like);
    }

    /**
    * @param string $table
    * @param int $uid
    * @return array|bool
    * @throws \Doctrine\DBAL\Exception
    */
    private static function preCheckRecordTable(string $table, int $uid): array|bool
    {
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            throw new \InvalidArgumentException('Table "' . $table . '" is not a valid content planner record table.', 9518991865);
        }

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!$record) {
            throw new \InvalidArgumentException('Record "' . $uid . '" in table "' . $table . '" not found.', 4064696674);
        }

        return $record;
    }
}
