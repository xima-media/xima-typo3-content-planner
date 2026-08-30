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
     * @throws Exception
     */
    public function processContentPlannerFields(array &$incomingFieldArray, string $table, int $id): void
    {
        // Reassigning a record does not touch the status field (see UrlUtility::getAssignUri()),
        // so gating on the status field alone meant a plain reassignment never reached
        // handleAssigneeChange(): no AssigneeChangedEvent, and therefore no assignment watcher.
        $hasStatusChange = isset($incomingFieldArray[Configuration::FIELD_STATUS]);
        $hasAssigneeChange = array_key_exists(Configuration::FIELD_ASSIGNEE, $incomingFieldArray);

        if (!$hasStatusChange && !$hasAssigneeChange) {
            return;
        }

        if (!$this->authoriseContentPlannerFields($incomingFieldArray, $table, $id, $hasStatusChange)) {
            return;
        }

        $preRecord = $this->recordRepository->findByUid($table, $id);
        if (false === $preRecord) {
            return;
        }

        $this->applyContentPlannerChanges($incomingFieldArray, $preRecord, $table, $id, $hasStatusChange);
    }

    /**
     * Mass-clear cascade (e.g. clearing all content elements under a page on page-status-reset,
     * or clearing every record referencing a deleted status). Deliberately dispatches no events
     * and creates no watcher relations: this is an administrative cascade over an arbitrary
     * number of records with no single meaningful "actor" or "previous/new status" pair per row,
     * so emitting one StatusChangeEvent per affected record would be both misleading and a
     * potential event storm. See Documentation/DeveloperCorner/Events.rst.
     */
    /** @phpstan-ignore typePerfect.narrowPublicClassMethodParamType */
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
     * @return bool false when nothing may be written at all
     *
     * @throws Exception
     */
    private function authoriseContentPlannerFields(array &$incomingFieldArray, string $table, int $id, bool $hasStatusChange): bool
    {
        $this->nullableField($incomingFieldArray, Configuration::FIELD_ASSIGNEE);
        if ($hasStatusChange) {
            $this->nullableField($incomingFieldArray, Configuration::FIELD_STATUS);
        }

        if (!PermissionUtility::isTableAllowedForUser($table)) {
            unset($incomingFieldArray[Configuration::FIELD_STATUS], $incomingFieldArray[Configuration::FIELD_ASSIGNEE]);

            return false;
        }

        if (!$hasStatusChange) {
            return true;
        }

        if (!$this->fieldAuthorizer->assertStatus($incomingFieldArray)) {
            return false;
        }

        $this->handleStatusReset($incomingFieldArray, $table, $id);

        return true;
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     *
     * @throws Exception
     */
    private function applyContentPlannerChanges(array &$incomingFieldArray, array $preRecord, string $table, int $id, bool $hasStatusChange): void
    {
        // Only relevant when a status is being written; it derives the assignee from it.
        if ($hasStatusChange) {
            $this->handleAutoAssignment($incomingFieldArray, $preRecord);
        }

        if (!$this->fieldAuthorizer->assertAssignee($incomingFieldArray, $preRecord)) {
            return;
        }

        $this->handleAssigneeChange($incomingFieldArray, $preRecord, $table, $id);

        if ($hasStatusChange) {
            $this->handleStatusChange($incomingFieldArray, $preRecord, $table, $id);
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    private function handleStatusReset(array &$incomingFieldArray, string $table, int $id): void
    {
        if (null !== $incomingFieldArray[Configuration::FIELD_STATUS]) {
            return;
        }

        $incomingFieldArray[Configuration::FIELD_ASSIGNEE] = null;

        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET)) {
            $this->commentRepository->deleteAllCommentsByRecord($id, $table);
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     */
    private function handleAutoAssignment(array &$incomingFieldArray, array $preRecord): void
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_AUTO_ASSIGN)) {
            return;
        }

        if (null === $incomingFieldArray[Configuration::FIELD_STATUS]) {
            return;
        }

        if (array_key_exists(Configuration::FIELD_ASSIGNEE, $incomingFieldArray)
            && null !== $incomingFieldArray[Configuration::FIELD_ASSIGNEE]) {
            return;
        }

        $hadNoStatusBefore = null === $preRecord[Configuration::FIELD_STATUS]
            || 0 === $preRecord[Configuration::FIELD_STATUS];

        if ($hadNoStatusBefore) {
            /** @var BackendUserAuthentication $backendUser */
            $backendUser = $GLOBALS['BE_USER'];
            $incomingFieldArray[Configuration::FIELD_ASSIGNEE] = $backendUser->getUserId();
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     */

    /**
     * Now that a reassignment reaches this method without a status change, the assignee value
     * has to be authorised here too. Previously the only gate was that the backend simply did
     * not render an action URL for a target the user may not pick, which does not survive a
     * hand-built request.
     *
     * @param array<string, mixed> $incomingFieldArray
     */

    /**
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
            $this->clearStatusOfExtensionRecords('tt_content', pid: $id);
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    private function nullableField(array &$incomingFieldArray, string $field): void
    {
        if (array_key_exists($field, $incomingFieldArray) && ('' === $incomingFieldArray[$field] || 0 === $incomingFieldArray[$field])) {
            $incomingFieldArray[$field] = null;
        }
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
