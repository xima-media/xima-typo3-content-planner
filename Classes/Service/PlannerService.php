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

namespace Xima\XimaTypo3ContentPlanner\Service;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\{GeneralUtility, StringUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{BackendUser, Status};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function is_array;
use function is_int;
use function is_string;

/**
 * PlannerService.
 *
 * Injectable service backing the static {@see \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility}
 * facade. Inject this service directly wherever constructor injection is available; the facade
 * exists only for callers that need a static entry point (e.g. third-party integrations).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class PlannerService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
    ) {}

    /**
     * @return Status[]
     */
    public function getListOfStatus(): array
    {
        return $this->statusRepository->findAll();
    }

    /**
     * @throws Exception
     */
    public function updateStatusForRecord(string $table, int $uid, Status|int|string $status, BackendUser|int|string|null $assignee = null): void
    {
        $this->preCheckRecordTable($table, $uid);

        $statusId = $status;
        if ($status instanceof Status) {
            $statusId = $status->getUid();
        } elseif (is_string($status)) {
            $statusId = $this->statusRepository->findByTitle($status)?->getUid();
        }

        if (!is_int($statusId) || 0 === $statusId) {
            throw new InvalidArgumentException('Status "'.$statusId.'" is not a valid content planner status.', 9220772840);
        }

        $assigneeId = $assignee;
        if ($assignee instanceof BackendUser) {
            $assigneeId = $assignee->getUid();
        } elseif (is_string($assignee)) {
            $assigneeId = $this->backendUserRepository->findByUsername($assignee);
            if ($assigneeId) {
                $assigneeId = (int) $assigneeId['uid'];
            }
        }

        $this->recordRepository->updateStatusByUid($table, $uid, $statusId, $assigneeId);
    }

    /**
     * @throws Exception
     */
    public function getStatusOfRecord(string $table, int $uid): ?Status
    {
        $record = $this->preCheckRecordTable($table, $uid);

        return $this->statusRepository->findByUid($record[Configuration::FIELD_STATUS]);
    }

    public function getStatus(int|string $identifier): ?Status
    {
        if (is_string($identifier)) {
            return $this->statusRepository->findByTitle($identifier);
        }

        return $this->statusRepository->findByUid($identifier);
    }

    /**
     * @param bool $showResolved include resolved comments and replies in the result
     *
     * @return array<int, array<string, mixed>>|array<int, \Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem>
     *
     * @throws Exception
     */
    public function getCommentsOfRecord(string $table, int $uid, bool $raw = false, bool $showResolved = false): array
    {
        $this->preCheckRecordTable($table, $uid);

        return $this->commentRepository->findAllByRecord($uid, $table, $raw, 'DESC', $showResolved);
    }

    /**
     * @param array<int,string>|string $comments
     * @param int                      $parentUid UID of the parent comment to reply to. Must belong to the same record. 0 creates a top-level comment.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function addCommentsToRecord(string $table, int $uid, array|string $comments, BackendUser|int|string|null $author = null, int $parentUid = 0): void
    {
        $record = $this->preCheckRecordTable($table, $uid);

        $authorId = $author;
        if ($author instanceof BackendUser) {
            $authorId = $author->getUid();
        } elseif (is_string($author)) {
            $user = $this->backendUserRepository->findByUsername($author);
            $authorId = is_array($user) && isset($user['uid']) ? (int) $user['uid'] : null;
        }

        if (!is_int($authorId) || 0 === $authorId) {
            throw new InvalidArgumentException('Author "'.$authorId.'" is not a valid backend user.', 4723563571);
        }

        $this->preCheckParentComment($table, $uid, $parentUid);

        if (!is_array($comments)) {
            $comments = [$comments];
        }

        $pid = 'pages' === $table ? $record['uid'] : $record['pid'];
        $data = [];

        foreach ($comments as $comment) {
            $newId = StringUtility::getUniqueId('NEW');
            $data[Configuration::TABLE_COMMENT][$newId] = [
                'foreign_uid' => $uid,
                'foreign_table' => $table,
                'content' => $comment,
                'pid' => $pid,
                'author' => $authorId,
                'parent_uid' => $parentUid,
            ];
        }

        // DataHandler is inherently a per-operation, stateful object (it accumulates its own
        // datamap/errorLog across calls), never a shared service - creating it via makeInstance()
        // here (rather than injecting it) is the correct TYPO3 pattern, not a DI shortcut.
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
    }

    /**
     * @throws Exception
     */
    public function clearCommentsOfRecord(string $table, int $uid, ?string $like = null): void
    {
        $this->preCheckRecordTable($table, $uid);

        $this->commentRepository->deleteAllCommentsByRecord($uid, $table, $like);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function preCheckRecordTable(string $table, int $uid): array
    {
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            throw new InvalidArgumentException('Table "'.$table.'" is not a valid content planner record table.', 9518991865);
        }

        $record = $this->recordRepository->findByUid($table, $uid);
        if (!$record) {
            throw new InvalidArgumentException('Record "'.$uid.'" in table "'.$table.'" not found.', 4064696674);
        }

        return $record;
    }

    private function preCheckParentComment(string $table, int $uid, int $parentUid): void
    {
        if (0 === $parentUid) {
            return;
        }

        if ($parentUid < 0) {
            throw new InvalidArgumentException('Parent comment UID must be zero or a valid positive UID.', 4723563572);
        }

        $parentComment = $this->commentRepository->findByUid($parentUid);
        if (!is_array($parentComment) || $parentComment['foreign_table'] !== $table || (int) $parentComment['foreign_uid'] !== $uid) {
            throw new InvalidArgumentException('Parent comment "'.$parentUid.'" does not exist or does not belong to the given record.', 4723563572);
        }
    }
}
