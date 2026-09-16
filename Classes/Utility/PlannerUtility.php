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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{BackendUser, Status};
use Xima\XimaTypo3ContentPlanner\Service\PlannerService;

/**
 * PlannerUtility.
 *
 * Thin static facade over the injectable {@see PlannerService}. Wherever constructor
 * injection is available (controllers, event listeners, services, ...), inject
 * PlannerService directly instead - it is easier to test and makes the dependency
 * explicit. This facade exists only so third-party integrations retain a stable,
 * static entry point.
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
        return self::service()->getListOfStatus();
    }

    /**
     * Simple function to update the status of a record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::updateStatusForRecord('pages', 1, 'In Progress', 'admin');.
     *
     * Note: this bypasses the DataHandler and writes the status directly via
     * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository::updateStatusByUid()}.
     * No StatusChangeEvent/AssigneeChangedEvent is dispatched and no watcher relation is created -
     * see that method's docblock for the reasoning.
     *
     * @throws Exception
     */
    public static function updateStatusForRecord(string $table, int $uid, Status|int|string $status, BackendUser|int|string|null $assignee = null): void
    {
        self::service()->updateStatusForRecord($table, $uid, $status, $assignee);
    }

    /**
     * Simple function to get the status of a record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getStatusOfRecord('pages', 1);.
     *
     * @throws Exception
     */
    public static function getStatusOfRecord(string $table, int $uid): ?Status
    {
        return self::service()->getStatusOfRecord($table, $uid);
    }

    /**
     * Simple function to get a status.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getStatus('Needs review');.
     */
    public static function getStatus(int|string $identifier): ?Status
    {
        return self::service()->getStatus($identifier);
    }

    /**
     * Simple function to fetch all comments of a record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::getCommentsOfRecord('pages', 1);.
     *
     * @param bool $showResolved include resolved comments and replies in the result
     *
     * @return array<int, array<string, mixed>>|array<int, \Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem>
     *
     * @throws Exception
     */
    public static function getCommentsOfRecord(string $table, int $uid, bool $raw = false, bool $showResolved = false): array
    {
        return self::service()->getCommentsOfRecord($table, $uid, $raw, $showResolved);
    }

    /**
     * Simple function to add comment(s) to a content planner record.
     * \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility::addCommentsToRecord('pages', 1, 'New Comment', 'admin');.
     *
     * @param array<int,string>|string $comments
     * @param int                      $parentUid UID of the parent comment to reply to. Must belong to the same record. 0 creates a top-level comment.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public static function addCommentsToRecord(string $table, int $uid, array|string $comments, BackendUser|int|string|null $author = null, int $parentUid = 0): void
    {
        self::service()->addCommentsToRecord($table, $uid, $comments, $author, $parentUid);
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
        self::service()->clearCommentsOfRecord($table, $uid, $like);
    }

    /**
     * Check if a record has comments.
     *
     * @param array<string, mixed> $record
     */
    public static function hasComments(array $record): bool
    {
        return isset($record[Configuration::FIELD_COMMENTS])
            && is_numeric($record[Configuration::FIELD_COMMENTS])
            && $record[Configuration::FIELD_COMMENTS] > 0;
    }

    private static function service(): PlannerService
    {
        return GeneralUtility::makeInstance(PlannerService::class);
    }
}
