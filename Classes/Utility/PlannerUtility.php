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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\{GeneralUtility, StringUtility};
use Xima\XimaTypo3ContentPlanner\Domain\Model\{BackendUser, Status};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository, StatusRepository};

use function is_array;
use function is_int;
use function is_string;

/**
 * PlannerUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class PlannerUtility
{
    /**
     * Simple function to get a list of all available status.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getListOfStatus();.
     *
     * @return Status[]
     */
    public static function getListOfStatus(): array
    {
        return GeneralUtility::makeInstance(StatusRepository::class)->findAll();
    }

    /**
     * Simple function to update the status of a record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::updateStatusForRecord('pages', 1, 'In Progress', 'admin');.
     *
     * @throws Exception
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

        if (!is_int($statusId) || 0 === $statusId) {
            throw new InvalidArgumentException('Status "'.$statusId.'" is not a valid content planner status.', 9220772840);
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
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getStatusOfRecord('pages', 1);.
     *
     * @throws Exception
     */
    public static function getStatusOfRecord(string $table, int $uid): ?Status
    {
        $record = self::preCheckRecordTable($table, $uid);

        return GeneralUtility::makeInstance(StatusRepository::class)->findByUid($record['tx_ximatypo3contentplanner_status']);
    }

    /**
     * Simple function to get a status.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getStatus('Needs review');.
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
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getCommentsOfRecord('pages', 1);.
     *
     * @return array<int, array<string, mixed>>|array<int, \Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem>
     *
     * @throws Exception
     */
    public static function getCommentsOfRecord(string $table, int $uid, bool $raw = false): array
    {
        self::preCheckRecordTable($table, $uid);

        return GeneralUtility::makeInstance(CommentRepository::class)->findAllByRecord($uid, $table, $raw);
    }

    /**
     * Simple function to add comment(s) to a content planner record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::addCommentsToRecord('pages', 1, 'New Comment', 'admin');.
     *
     * @param array<int,string>|string $comments
     * @param BackendUser|int|string   $author
     *
     * @throws Exception
     */
    public static function addCommentsToRecord(string $table, int $uid, array|string $comments, BackendUser|int|string|null $author = null): void
    {
        $record = self::preCheckRecordTable($table, $uid);

        $authorId = $author;
        if ($author instanceof BackendUser) {
            $authorId = $author->getUid();
        } elseif (is_string($author)) {
            $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
            $user = $backendUserRepository->findByUsername($author);
            $authorId = is_array($user) && isset($user['uid']) ? (int) $user['uid'] : null;
        }

        if (!is_int($authorId) || 0 === $authorId) {
            throw new InvalidArgumentException('Author "'.$authorId.'" is not a valid backend user.', 4723563571);
        }

        if (!is_array($comments)) {
            $comments = [$comments];
        }

        $pid = 'pages' === $table ? $record['uid'] : $record['pid'];
        $newIds = [];
        $data = [];

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

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
    }

    /**
     * Simple function to generate the html todo markup for a comment to easily insert them into the comment content.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::generateTodoForComment(['First todo', 'Second todo']);.
     *
     * @param string[] $todos
     */
    public static function generateTodoForComment(array $todos): string
    {
        $html = '<ul class="todo-list">';
        foreach ($todos as $todo) {
            $html .= '<li><label class="todo-list__label">'
                .'<input type="checkbox" disabled="disabled">'
                .'<span class="todo-list__label__description">'.htmlspecialchars($todo, \ENT_QUOTES | \ENT_HTML5).'</span>'
                .'</label></li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Simple function to clear all comment(s) of a content planner record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::clearCommentsOfRecord('pages', 1);.
     *
     * @throws Exception
     */
    public static function clearCommentsOfRecord(string $table, int $uid, ?string $like = null): void
    {
        self::preCheckRecordTable($table, $uid);

        $commentsRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $commentsRepository->deleteAllCommentsByRecord($uid, $table, $like);
    }

    /**
     * Check if a record has comments.
     *
     * @param array<string, mixed> $record
     */
    public static function hasComments(array $record): bool
    {
        return isset($record['tx_ximatypo3contentplanner_comments'])
            && is_numeric($record['tx_ximatypo3contentplanner_comments'])
            && $record['tx_ximatypo3contentplanner_comments'] > 0;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private static function preCheckRecordTable(string $table, int $uid): array
    {
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            throw new InvalidArgumentException('Table "'.$table.'" is not a valid content planner record table.', 9518991865);
        }

        $record = GeneralUtility::makeInstance(RecordRepository::class)->findByUid($table, $uid);
        if (!$record) {
            throw new InvalidArgumentException('Record "'.$uid.'" in table "'.$table.'" not found.', 4064696674);
        }

        return $record;
    }
}
