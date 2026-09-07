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

namespace Xima\XimaTypo3ContentPlanner\Manager;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Event\{AssigneeChangedEvent, StatusChangeEvent};
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function is_array;

/**
 * StatusChangeManager.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusChangeManager
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ConnectionPool $connectionPool,
        private readonly ContentPlannerFieldAuthorizer $fieldAuthorizer,
    ) {}

    /**
     * @param array<string, mixed> $incomingFieldArray
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function processContentPlannerFields(array $incomingFieldArray, string $table, int $id): array
    {
        // Reassigning a record does not touch the status field (see UrlUtility::getAssignUri()),
        // so gating on the status field alone meant a plain reassignment never reached
        // handleAssigneeChange(): no AssigneeChangedEvent, and therefore no assignment watcher.
        $hasStatusChange = isset($incomingFieldArray[Configuration::FIELD_STATUS]);
        $hasAssigneeChange = array_key_exists(Configuration::FIELD_ASSIGNEE, $incomingFieldArray);

        if (!$hasStatusChange && !$hasAssigneeChange) {
            return $incomingFieldArray;
        }

        [$incomingFieldArray, $authorised] = $this->authoriseContentPlannerFields($incomingFieldArray, $table, $id, $hasStatusChange);
        if (!$authorised) {
            return $incomingFieldArray;
        }

        $preRecord = $this->recordRepository->findByUid($table, $id);
        if (false === $preRecord) {
            return $incomingFieldArray;
        }

        return $this->applyContentPlannerChanges($incomingFieldArray, $preRecord, $table, $id, $hasStatusChange);
    }

    /**
     * Mass-clear cascade (e.g. clearing all content elements under a page on page-status-reset,
     * or clearing every record referencing a deleted status). Deliberately dispatches no events
     * and creates no watcher relations: this is an administrative cascade over an arbitrary
     * number of records with no single meaningful "actor" or "previous/new status" pair per row,
     * so emitting one StatusChangeEvent per affected record would be both misleading and a
     * potential event storm. See Documentation/DeveloperCorner/Events.rst.
     */
    public function clearStatusOfExtensionRecords(string $table, ?int $status = null, ?int $pid = null): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set(Configuration::FIELD_STATUS, null)
        ;

        if ((bool) $status) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(Configuration::FIELD_STATUS, $status),
            );
        }

        if ((bool) $pid) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('pid', $pid),
            );
        }

        $queryBuilder->executeStatement();
    }

    /**
     * Normalises the incoming values and strips whatever the current user may not write.
     *
     * @param array<string, mixed> $incomingFieldArray
     *
     * @return array{0: array<string, mixed>, 1: bool} the (possibly modified) field array and whether anything may be written at all
     *
     * @throws Exception
     */
    private function authoriseContentPlannerFields(array $incomingFieldArray, string $table, int $id, bool $hasStatusChange): array
    {
        $incomingFieldArray = $this->nullableField($incomingFieldArray, Configuration::FIELD_ASSIGNEE);
        if ($hasStatusChange) {
            $incomingFieldArray = $this->nullableField($incomingFieldArray, Configuration::FIELD_STATUS);
        }

        if (!PermissionUtility::isTableAllowedForUser($table)) {
            unset($incomingFieldArray[Configuration::FIELD_STATUS], $incomingFieldArray[Configuration::FIELD_ASSIGNEE]);

            return [$incomingFieldArray, false];
        }

        if (!$hasStatusChange) {
            return [$incomingFieldArray, true];
        }

        [$incomingFieldArray, $allowed] = $this->fieldAuthorizer->assertStatus($incomingFieldArray);
        if (!$allowed) {
            return [$incomingFieldArray, false];
        }

        $incomingFieldArray = $this->handleStatusReset($incomingFieldArray, $table, $id);

        return [$incomingFieldArray, true];
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function applyContentPlannerChanges(array $incomingFieldArray, array $preRecord, string $table, int $id, bool $hasStatusChange): array
    {
        // Only relevant when a status is being written; it derives the assignee from it.
        if ($hasStatusChange) {
            $incomingFieldArray = $this->handleAutoAssignment($incomingFieldArray, $preRecord);
        }

        [$incomingFieldArray, $allowed] = $this->fieldAuthorizer->assertAssignee($incomingFieldArray, $preRecord);
        if ($allowed) {
            $this->handleAssigneeChange($incomingFieldArray, $preRecord, $table, $id);
        }

        if ($hasStatusChange) {
            $this->handleStatusChange($incomingFieldArray, $preRecord, $table, $id);
        }

        return $incomingFieldArray;
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     *
     * @return array<string, mixed>
     */
    private function handleStatusReset(array $incomingFieldArray, string $table, int $id): array
    {
        if (null !== $incomingFieldArray[Configuration::FIELD_STATUS]) {
            return $incomingFieldArray;
        }

        $incomingFieldArray[Configuration::FIELD_ASSIGNEE] = null;

        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET)) {
            $this->commentRepository->deleteAllCommentsByRecord($id, $table);
        }

        return $incomingFieldArray;
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     *
     * @return array<string, mixed>
     */
    private function handleAutoAssignment(array $incomingFieldArray, array $preRecord): array
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_AUTO_ASSIGN)) {
            return $incomingFieldArray;
        }

        if (null === $incomingFieldArray[Configuration::FIELD_STATUS]) {
            return $incomingFieldArray;
        }

        if (array_key_exists(Configuration::FIELD_ASSIGNEE, $incomingFieldArray)
            && null !== $incomingFieldArray[Configuration::FIELD_ASSIGNEE]) {
            return $incomingFieldArray;
        }

        $hadNoStatusBefore = null === $preRecord[Configuration::FIELD_STATUS]
            || 0 === $preRecord[Configuration::FIELD_STATUS];

        if ($hadNoStatusBefore) {
            /** @var BackendUserAuthentication $backendUser */
            $backendUser = $GLOBALS['BE_USER'];
            $incomingFieldArray[Configuration::FIELD_ASSIGNEE] = $backendUser->getUserId();
        }

        return $incomingFieldArray;
    }

    /**
     * Now that a reassignment reaches this method without a status change, the assignee value
     * has to be authorised here too. Previously the only gate was that the backend simply did
     * not render an action URL for a target the user may not pick, which does not survive a
     * hand-built request.
     *
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     */
    private function handleAssigneeChange(array $incomingFieldArray, array $preRecord, string $table, int $id): void
    {
        if (!array_key_exists(Configuration::FIELD_ASSIGNEE, $incomingFieldArray)) {
            return;
        }

        $previousAssignee = isset($preRecord[Configuration::FIELD_ASSIGNEE]) && is_numeric($preRecord[Configuration::FIELD_ASSIGNEE]) && $preRecord[Configuration::FIELD_ASSIGNEE] > 0 ? (int) $preRecord[Configuration::FIELD_ASSIGNEE] : null;
        $newAssignee = null !== $incomingFieldArray[Configuration::FIELD_ASSIGNEE] ? (int) $incomingFieldArray[Configuration::FIELD_ASSIGNEE] : null;

        if ($previousAssignee === $newAssignee) {
            return;
        }

        $this->eventDispatcher->dispatch(new AssigneeChangedEvent($table, $id, $previousAssignee, $newAssignee, PermissionUtility::getCurrentUserId()));
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     *
     * @throws Exception
     */
    private function handleStatusChange(array $incomingFieldArray, array $preRecord, string $table, int $id): void
    {
        if (!$this->isStatusFieldChanged($incomingFieldArray, $preRecord)) {
            return;
        }

        $previousStatus = isset($preRecord[Configuration::FIELD_STATUS]) && is_numeric($preRecord[Configuration::FIELD_STATUS]) && $preRecord[Configuration::FIELD_STATUS] > 0 ? ContentUtility::getStatus((int) $preRecord[Configuration::FIELD_STATUS]) : null;
        $newStatus = isset($incomingFieldArray[Configuration::FIELD_STATUS]) && is_numeric($incomingFieldArray[Configuration::FIELD_STATUS]) && $incomingFieldArray[Configuration::FIELD_STATUS] > 0 ? ContentUtility::getStatus((int) $incomingFieldArray[Configuration::FIELD_STATUS]) : null;
        $this->eventDispatcher->dispatch(new StatusChangeEvent($table, $id, $incomingFieldArray, $previousStatus, $newStatus, PermissionUtility::getCurrentUserId()));

        if (null === $incomingFieldArray[Configuration::FIELD_STATUS] && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET)) {
            $this->clearStatusOfExtensionRecords('tt_content', null, $id);
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     *
     * @return array<string, mixed>
     */
    private function nullableField(array $incomingFieldArray, string $field): array
    {
        if (array_key_exists($field, $incomingFieldArray) && ('' === $incomingFieldArray[$field] || 0 === $incomingFieldArray[$field])) {
            $incomingFieldArray[$field] = null;
        }

        return $incomingFieldArray;
    }

    /**
     * @param array<string, mixed>      $incomingFieldArray
     * @param array<string, mixed>|bool $preRecord
     */
    private function isStatusFieldChanged(array $incomingFieldArray, array|bool $preRecord): bool
    {
        if (!is_array($preRecord)) {
            return false;
        }

        return $preRecord[Configuration::FIELD_STATUS] !== $incomingFieldArray[Configuration::FIELD_STATUS];
    }
}
