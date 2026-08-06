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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary\{AssigneeSummary, CapabilitySummary, CommentSummary, RecordSummary, StatusSummary};
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;

/**
 * RecordSummaryService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordSummaryService
{
    /** @var array<int, Status|null> */
    private array $statusCache = [];

    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
    ) {}

    /**
     * Builds a summary per requested record, batching every lookup so the cost is a
     * handful of queries per table rather than a handful per record.
     *
     * Records the current user may not see are omitted rather than reported as an error,
     * so one forbidden element cannot spoil a whole page's request.
     *
     * @param array<int, array{table: string, uid: int}> $items
     *
     * @return RecordSummary[]
     *
     * @throws Exception
     */
    public function buildForItems(array $items): array
    {
        if (!$this->isVisible()) {
            return [];
        }

        $summaries = [];
        foreach ($this->groupUidsByTable($items) as $table => $uids) {
            foreach ($this->buildForTable($table, $uids) as $summary) {
                $summaries[] = $summary;
            }
        }

        return $summaries;
    }

    /*
     * Everything that reads global TYPO3 state — permissions and extension
     * configuration — sits behind these seams, so the batching behaviour can be
     * exercised without a backend user session or a booted extension configuration.
     */

    protected function areTodosEnabled(): bool
    {
        return ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS);
    }

    protected function isVisible(): bool
    {
        return PermissionUtility::checkContentStatusVisibility();
    }

    protected function isTableUsable(string $table): bool
    {
        return ExtensionUtility::isRegisteredRecordTable($table) && PermissionUtility::isTableAllowedForUser($table);
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function isRecordAccessible(string $table, array $record): bool
    {
        return PermissionUtility::checkAccessForRecord($table, $record);
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function capabilitiesFor(array $record): CapabilitySummary
    {
        $statusUid = (int) ($record[Configuration::FIELD_STATUS] ?? 0);

        return new CapabilitySummary(
            canChangeStatus: PermissionUtility::canChangeStatus(0 !== $statusUid ? $statusUid : null),
            canUnsetStatus: PermissionUtility::canUnsetStatus(),
            canComment: PermissionUtility::canCreateComment(),
        );
    }

    /**
     * @param int[] $uids
     *
     * @return RecordSummary[]
     *
     * @throws Exception
     */
    private function buildForTable(string $table, array $uids): array
    {
        // findAllByUids() whitelists the table itself, but bailing out early also skips
        // the comment and assignee queries for a table this user may not read at all.
        if (!$this->isTableUsable($table)) {
            return [];
        }

        $records = $this->recordRepository->findAllByUids($table, $uids, ignoreVisibilityRestriction: true);
        if ([] === $records) {
            return [];
        }

        $accessible = [];
        foreach ($records as $uid => $record) {
            if ($this->isRecordAccessible($table, $record)) {
                $accessible[$uid] = $record;
            }
        }

        if ([] === $accessible) {
            return [];
        }

        $accessibleUids = array_keys($accessible);
        $commentCounts = $this->commentRepository->countAllByRecords($table, $accessibleUids);
        $todoCounts = $this->todoCountsFor($table, $accessibleUids);
        $assigneeNames = $this->assigneeNamesFor($accessible);

        $summaries = [];
        foreach ($accessible as $uid => $record) {
            $summaries[] = new RecordSummary(
                table: $table,
                uid: $uid,
                status: $this->statusSummaryFor($record),
                assignee: $this->assigneeSummaryFor($record, $assigneeNames),
                comments: new CommentSummary(
                    total: $commentCounts[$uid] ?? 0,
                    todoTotal: $todoCounts[$uid]['total'] ?? 0,
                    todoResolved: $todoCounts[$uid]['resolved'] ?? 0,
                ),
                capabilities: $this->capabilitiesFor($record),
            );
        }

        return $summaries;
    }

    /**
     * @param int[] $uids
     *
     * @return array<int, array{total: int, resolved: int}>
     *
     * @throws Exception
     */
    private function todoCountsFor(string $table, array $uids): array
    {
        // Mirrors StatusItem, which reports no to-dos at all while the feature is off.
        if (!$this->areTodosEnabled()) {
            return [];
        }

        return $this->commentRepository->countTodosByRecords($table, $uids);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     *
     * @return array<int, string>
     *
     * @throws Exception
     */
    private function assigneeNamesFor(array $records): array
    {
        $assigneeUids = [];
        foreach ($records as $record) {
            $assigneeUid = (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);
            if (0 !== $assigneeUid) {
                $assigneeUids[$assigneeUid] = $assigneeUid;
            }
        }

        return [] === $assigneeUids ? [] : $this->backendUserRepository->getDisplayNamesByUids(array_values($assigneeUids));
    }

    /**
     * @param array<string, mixed> $record
     */
    private function statusSummaryFor(array $record): ?StatusSummary
    {
        $statusUid = (int) ($record[Configuration::FIELD_STATUS] ?? 0);
        if (0 === $statusUid) {
            return null;
        }

        $status = $this->resolveStatus($statusUid);

        return $status instanceof Status ? StatusSummary::fromStatus($status) : null;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, string>   $assigneeNames
     */
    private function assigneeSummaryFor(array $record, array $assigneeNames): ?AssigneeSummary
    {
        $assigneeUid = (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);
        if (0 === $assigneeUid || !array_key_exists($assigneeUid, $assigneeNames)) {
            return null;
        }

        return new AssigneeSummary(uid: $assigneeUid, displayName: $assigneeNames[$assigneeUid]);
    }

    private function resolveStatus(int $statusUid): ?Status
    {
        return $this->statusCache[$statusUid] ??= ContentUtility::getStatus($statusUid);
    }

    /**
     * @param array<int, array{table: string, uid: int}> $items
     *
     * @return array<string, int[]>
     */
    private function groupUidsByTable(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $table = $item['table'];
            $uid = $item['uid'];
            if ('' === $table || $uid <= 0) {
                continue;
            }
            $grouped[$table][$uid] = $uid;
        }

        return array_map(array_values(...), $grouped);
    }
}
