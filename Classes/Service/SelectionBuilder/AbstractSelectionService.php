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

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;

/**
 * AbstractSelectionService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class AbstractSelectionService
{
    public function __construct(
        protected readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly StatusSelectionManager $statusSelectionManager,
        protected readonly CommentRepository $commentRepository,
        protected readonly SelectionUriBuilder $selectionUriBuilder,
        protected readonly FolderStatusRepository $folderStatusRepository,
    ) {}

    /**
     * @return array<string, mixed>|bool
     *
     * @throws NotImplementedException|Exception
     */
    public function generateSelection(string $table, int $uid): array|bool
    {
        if (!$this->shouldGenerateSelection($table)) {
            return false;
        }

        $allStatus = $this->statusRepository->findAll();
        if (0 === count($allStatus)) {
            return false;
        }

        $record = $this->getCurrentRecord($table, $uid);
        $selectionEntriesToAdd = [];

        $this->addHeaderItemToSelection($selectionEntriesToAdd);
        $this->addAllStatusItems($selectionEntriesToAdd, $allStatus, $record, $table, $uid);
        $this->addStatusResetSection($selectionEntriesToAdd, $record, $table, $uid);
        $this->addAdditionalActionsSection($selectionEntriesToAdd, $record, $table, $uid);

        $this->statusSelectionManager->prepareStatusSelection($this, $table, $uid, $selectionEntriesToAdd, $this->getCurrentStatus($record));

        return $selectionEntriesToAdd;
    }

    public function shouldGenerateSelection(string $table): bool
    {
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            return false;
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return false;
        }

        // Check if user has permission for this table
        if (!PermissionUtility::isTableAllowedForUser($table)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     *
     * @throws NotImplementedException
     */
    public function addHeaderItemToSelection(array &$selectionEntriesToAdd): void
    {
        throw new NotImplementedException('Method not implemented', 1741960484);
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<int, int>|int|null       $uid
     * @param array<string, mixed>|bool|null $record
     *
     * @throws NotImplementedException
     */
    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960485);
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<int, int>|int|null       $uid
     * @param array<string, mixed>|bool|null $record
     *
     * @throws NotImplementedException
     */
    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960486);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws NotImplementedException
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, string $table, int $uid): void
    {
        throw new NotImplementedException('Method not implemented', 1741960487);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws NotImplementedException
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, string $table, int $uid): void
    {
        throw new NotImplementedException('Method not implemented', 1741960488);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws NotImplementedException
     */
    public function addCommentsTodoItemToSelection(array &$selectionEntriesToAdd, array $record, string $table, int $uid): void
    {
        throw new NotImplementedException('Method not implemented', 1741960489);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     *
     * @throws NotImplementedException
     */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960490);
    }

    /**
     * Generate selection items for a folder.
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception|RouteNotFoundException
     * @throws NotImplementedException
     */
    public function generateFolderSelection(string $combinedIdentifier): array|false
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return false;
        }

        $allStatus = $this->statusRepository->findAll();
        if (0 === count($allStatus)) {
            return false;
        }

        $folderRecord = $this->folderStatusRepository->findByCombinedIdentifier($combinedIdentifier);
        $currentStatus = $this->getFolderCurrentStatus($folderRecord);

        $selectionEntriesToAdd = [];

        $this->addHeaderItemToSelection($selectionEntriesToAdd);
        $this->addAllFolderStatusItems($selectionEntriesToAdd, $allStatus, $currentStatus, $combinedIdentifier);
        $this->addFolderStatusResetSection($selectionEntriesToAdd, $currentStatus, $combinedIdentifier);
        $this->addFolderAdditionalActionsSection($selectionEntriesToAdd, $folderRecord, $currentStatus, $combinedIdentifier);

        return $selectionEntriesToAdd;
    }

    /**
     * @param array<int|string, mixed> $selectionEntriesToAdd
     *
     * @throws NotImplementedException
     */
    public function addFolderStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, ?int $currentStatus, string $combinedIdentifier): void
    {
        throw new NotImplementedException('Method not implemented', 1741960491);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     *
     * @throws NotImplementedException
     */
    public function addFolderStatusResetItemToSelection(array &$selectionEntriesToAdd, string $combinedIdentifier): void
    {
        throw new NotImplementedException('Method not implemented', 1741960492);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $folderRecord
     *
     * @throws NotImplementedException
     */
    public function addFolderAssigneeItemToSelection(array &$selectionEntriesToAdd, array $folderRecord, string $combinedIdentifier): void
    {
        throw new NotImplementedException('Method not implemented', 1741960493);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $folderRecord
     *
     * @throws NotImplementedException
     */
    public function addFolderCommentsItemToSelection(array &$selectionEntriesToAdd, array $folderRecord, string $combinedIdentifier): void
    {
        throw new NotImplementedException('Method not implemented', 1741960494);
    }

    protected function buildUriForFolderStatusChange(string $combinedIdentifier, ?Status $status): UriInterface
    {
        return $this->selectionUriBuilder->buildUriForFolderStatusChange($combinedIdentifier, $status);
    }

    /**
     * @return array<string, mixed>|bool|null
     *
     * @throws Exception
     */
    protected function getCurrentRecord(string $table, int $uid): array|bool|null
    {
        return $this->recordRepository->findByUid($table, $uid, true);
    }

    /**
     * @param array<string, mixed>|bool|null $record
     */
    protected function getCurrentStatus(array|bool|null $record = null): ?int
    {
        if (!is_array($record) || !isset($record[Configuration::FIELD_STATUS])) {
            return null;
        }

        return (int) $record[Configuration::FIELD_STATUS] ?: null;
    }

    protected function compareStatus(Status $status, Status|int|null $currentStatus): bool
    {
        if (null === $currentStatus) {
            return false;
        }

        if ($currentStatus instanceof Status) {
            return $status->getUid() === $currentStatus->getUid();
        }

        return $status->getUid() === $currentStatus;
    }

    /**
     * @param array<int, int>|int $uid
     *
     * @throws RouteNotFoundException
     */
    protected function buildUriForStatusChange(string $table, array|int $uid, ?Status $status): UriInterface
    {
        return $this->selectionUriBuilder->buildUriForStatusChange($table, $uid, $status);
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function getCommentsTodoResolved(array $record, string $table): int
    {
        return PlannerUtility::hasComments($record) ? $this->commentRepository->countTodoAllByRecord((int) $record['uid'], $table) : 0;
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function getCommentsTodoTotal(array $record, string $table): int
    {
        return PlannerUtility::hasComments($record) ? $this->commentRepository->countTodoAllByRecord((int) $record['uid'], $table, 'todo_total') : 0;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @param array<string, mixed>|false $folderRecord
     */
    private function getFolderCurrentStatus(array|false $folderRecord): ?int
    {
        if (!is_array($folderRecord) || !isset($folderRecord[Configuration::FIELD_STATUS])) {
            return null;
        }

        return 0 !== (int) $folderRecord[Configuration::FIELD_STATUS] ? (int) $folderRecord[Configuration::FIELD_STATUS] : null;
    }

    /**
     * @param array<int|string, mixed> $selectionEntriesToAdd
     * @param array<int, Status>       $allStatus
     *
     * @throws NotImplementedException
     */
    private function addAllFolderStatusItems(array &$selectionEntriesToAdd, array $allStatus, ?int $currentStatus, string $combinedIdentifier): void
    {
        if (!PermissionUtility::canChangeStatus()) {
            return;
        }

        foreach ($allStatus as $statusItem) {
            if (!PermissionUtility::isStatusAllowedForUser($statusItem->getUid())) {
                continue;
            }
            $this->addFolderStatusItemToSelection($selectionEntriesToAdd, $statusItem, $currentStatus, $combinedIdentifier);
        }
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     *
     * @throws NotImplementedException
     */
    private function addFolderStatusResetSection(array &$selectionEntriesToAdd, ?int $currentStatus, string $combinedIdentifier): void
    {
        if (null === $currentStatus || !PermissionUtility::canUnsetStatus()) {
            return;
        }

        if ([] !== $selectionEntriesToAdd) {
            $this->addDividerItemToSelection($selectionEntriesToAdd);
        }
        $this->addFolderStatusResetItemToSelection($selectionEntriesToAdd, $combinedIdentifier);
    }

    /**
     * @param array<string, mixed>       $selectionEntriesToAdd
     * @param array<string, mixed>|false $folderRecord
     *
     * @throws NotImplementedException
     */
    private function addFolderAdditionalActionsSection(array &$selectionEntriesToAdd, array|false $folderRecord, ?int $currentStatus, string $combinedIdentifier): void
    {
        if (!is_array($folderRecord) || null === $currentStatus) {
            return;
        }

        $this->addDividerItemToSelection($selectionEntriesToAdd, '2');
        $this->addFolderAssigneeItemToSelection($selectionEntriesToAdd, $folderRecord, $combinedIdentifier);
        $this->addFolderCommentsItemToSelection($selectionEntriesToAdd, $folderRecord, $combinedIdentifier);
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<int, Status>             $allStatus
     * @param array<string, mixed>|bool|null $record
     *
     * @throws NotImplementedException
     */
    private function addAllStatusItems(array &$selectionEntriesToAdd, array $allStatus, array|bool|null $record, string $table, int $uid): void
    {
        // Check if user can change status at all
        if (!PermissionUtility::canChangeStatus()) {
            return;
        }

        foreach ($allStatus as $statusItem) {
            // Only add status if user is allowed to use it
            if (!PermissionUtility::isStatusAllowedForUser($statusItem->getUid())) {
                continue;
            }

            $this->addStatusItemToSelection($selectionEntriesToAdd, $statusItem, $this->getCurrentStatus($record), $table, $uid, $record);
        }
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<string, mixed>|bool|null $record
     *
     * @throws NotImplementedException
     */
    private function addStatusResetSection(array &$selectionEntriesToAdd, array|bool|null $record, string $table, int $uid): void
    {
        // Check if user can unset status
        if (!PermissionUtility::canUnsetStatus()) {
            return;
        }

        if (!is_array($record) || (null !== $record[Configuration::FIELD_STATUS] && 0 !== $record[Configuration::FIELD_STATUS])) {
            if ([] !== $selectionEntriesToAdd) {
                $this->addDividerItemToSelection($selectionEntriesToAdd);
            }
            $this->addStatusResetItemToSelection($selectionEntriesToAdd, $table, $uid, $record);
        }
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<string, mixed>|bool|null $record
     *
     * @throws NotImplementedException
     */
    private function addAdditionalActionsSection(array &$selectionEntriesToAdd, array|bool|null $record, string $table, int $uid): void
    {
        if (!is_array($record) || null === $this->getCurrentStatus($record)) {
            return;
        }

        $this->addDividerItemToSelection($selectionEntriesToAdd, '2');
        $this->addAssigneeItemToSelection($selectionEntriesToAdd, $record, $table, $uid);
        $this->addCommentsItemToSelection($selectionEntriesToAdd, $record, $table, $uid);

        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            $this->addCommentsTodoItemToSelection($selectionEntriesToAdd, $record, $table, $uid);
        }
    }
}
