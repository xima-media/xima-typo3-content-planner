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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary\{StatusSelection, StatusSelectionItem, StatusSummary};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * StatusSelectionApiService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusSelectionApiService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly StatusSelectionManager $statusSelectionManager,
    ) {}

    /**
     * Builds the selectable statuses for a record, running PrepareStatusSelectionEvent so
     * a project listener restricts the API exactly as it restricts the backend menus.
     *
     * The selection handed to the event is keyed by status uid, the convention every
     * backend selection builder already follows, so a listener unsetting a status by key
     * takes effect here unchanged. A listener that instead rewrites entry *values* has to
     * branch on the event's context, which it must do anyway — the value shape already
     * differs between the list, dropdown and context-menu builders.
     *
     * @return StatusSelection|null null when the record is not readable for this user
     *
     * @throws Exception
     */
    public function buildForRecord(string $table, int $uid): ?StatusSelection
    {
        if (!$this->isVisible() || !$this->isTableUsable($table)) {
            return null;
        }

        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);
        if (!is_array($record) || !$this->isRecordAccessible($table, $record)) {
            return null;
        }

        $currentStatusUid = (int) ($record[Configuration::FIELD_STATUS] ?? 0);
        $candidates = $this->collectCandidates($currentStatusUid);

        $selection = $candidates;
        $this->statusSelectionManager->prepareStatusSelection(
            $this,
            $table,
            $uid,
            $selection,
            0 !== $currentStatusUid ? $currentStatusUid : null,
        );

        $items = [];
        foreach ($selection as $key => $entry) {
            // Only keys that were offered as statuses are reported; a listener adding its
            // own unrelated entries cannot inject them into the status list.
            if ($entry instanceof StatusSelectionItem && isset($candidates[$key])) {
                $items[] = $entry;
            }
        }

        return new StatusSelection(
            table: $table,
            uid: $uid,
            items: $items,
            canUnset: 0 !== $currentStatusUid && $this->canUnsetStatus(),
        );
    }

    /*
     * Global TYPO3 state sits behind these seams, mirroring RecordSummaryService.
     */

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

    protected function canChangeStatus(): bool
    {
        return PermissionUtility::canChangeStatus();
    }

    protected function canUnsetStatus(): bool
    {
        return PermissionUtility::canUnsetStatus();
    }

    protected function isStatusAllowed(int $statusUid): bool
    {
        return PermissionUtility::isStatusAllowedForUser($statusUid);
    }

    /**
     * Mirrors AbstractSelectionService::addAllStatusItems(), which is what makes the
     * response honour `allowed_statuses` from be_groups.
     *
     * Keys are written as strings to match the backend selection builders, but PHP
     * normalises numeric keys to integers — which is why a listener may unset either
     * form.
     *
     * @return array<array-key, StatusSelectionItem>
     */
    private function collectCandidates(int $currentStatusUid): array
    {
        if (!$this->canChangeStatus()) {
            return [];
        }

        $candidates = [];
        foreach ($this->statusRepository->findAll() as $status) {
            $statusUid = (int) $status->getUid();
            if (!$this->isStatusAllowed($statusUid)) {
                continue;
            }

            $candidates[(string) $statusUid] = new StatusSelectionItem(
                status: StatusSummary::fromStatus($status),
                current: $statusUid === $currentStatusUid,
            );
        }

        return $candidates;
    }
}
