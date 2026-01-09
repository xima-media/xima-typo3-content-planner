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
use Psr\Http\Message\{ServerRequestInterface, UriInterface};
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;
use function is_int;

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
        protected readonly UriBuilder $uriBuilder,
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
        $allStatus = $this->statusRepository->findAll();
        if (0 === count($allStatus)) {
            return false;
        }

        $folderRecord = $this->folderStatusRepository->findByCombinedIdentifier($combinedIdentifier);
        $currentStatus = null;
        if (is_array($folderRecord) && isset($folderRecord[Configuration::FIELD_STATUS]) && 0 !== (int) $folderRecord[Configuration::FIELD_STATUS]) {
            $currentStatus = (int) $folderRecord[Configuration::FIELD_STATUS];
        }

        $selectionEntriesToAdd = [];

        $this->addHeaderItemToSelection($selectionEntriesToAdd);
        foreach ($allStatus as $statusItem) {
            $this->addFolderStatusItemToSelection($selectionEntriesToAdd, $statusItem, $currentStatus, $combinedIdentifier);
        }

        // Add reset option if status is set
        if (null !== $currentStatus) {
            if ([] !== $selectionEntriesToAdd) {
                $this->addDividerItemToSelection($selectionEntriesToAdd);
            }
            $this->addFolderStatusResetItemToSelection($selectionEntriesToAdd, $combinedIdentifier);
        }

        // Add additional actions (assignee, comments) if folder record exists
        if (is_array($folderRecord) && null !== $currentStatus) {
            $this->addDividerItemToSelection($selectionEntriesToAdd, '2');
            $this->addFolderAssigneeItemToSelection($selectionEntriesToAdd, $folderRecord, $combinedIdentifier);
            $this->addFolderCommentsItemToSelection($selectionEntriesToAdd, $folderRecord, $combinedIdentifier);
        }

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

    /**
     * @throws RouteNotFoundException
     */
    protected function buildUriForFolderStatusChange(string $combinedIdentifier, ?Status $status): UriInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $currentFolderId = $request->getQueryParams()['id'] ?? '';

        return $this->uriBuilder->buildUriFromRoute(
            'ximatypo3contentplanner_folder_status_update',
            [
                'identifier' => $combinedIdentifier,
                'status' => $status instanceof Status ? $status->getUid() : 0,
                'redirect' => (string) $this->uriBuilder->buildUriFromRoute(
                    'ximatypo3contentplanner_message',
                    [
                        'redirect' => (string) $this->uriBuilder->buildUriFromRoute('media_management', ['id' => $currentFolderId]),
                        'message' => $status instanceof Status ? 'status.changed' : 'status.reset',
                    ],
                ),
            ],
        );
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
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $route = $request->getAttribute('routing')->getRoute()->getOption('_identifier');

        $routeArray = $this->buildRouteArrayForRoute($route, $table, $uid, $request);

        $dataArray = [
            $table => [],
        ];
        if (is_int($uid)) {
            $uid = [$uid];
        }
        foreach ($uid as $singleId) {
            $dataArray[$table][$singleId] = [
                Configuration::FIELD_STATUS => $status instanceof Status ? $status->getUid() : '',
            ];
        }

        return $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => $dataArray,
                'redirect' => $this->uriBuilder->buildUriFromRoute(
                    'ximatypo3contentplanner_message',
                    [
                        'redirect' => (string) $this->uriBuilder->buildUriFromRoute($route, $routeArray),
                        'message' => $status instanceof Status ? 'status.changed' : 'status.reset',
                    ],
                ),
            ],
        );
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
     * @param array<int, int>|int $uid
     *
     * @return array<string, mixed>
     */
    private function buildRouteArrayForRoute(string $route, string $table, array|int $uid, ServerRequestInterface $request): array
    {
        if ('record_edit' === $route) {
            return [
                'edit' => [
                    $table => [
                        $uid => 'edit',
                    ],
                ],
            ];
        }

        if (RouteUtility::isRecordListRoute($route)) {
            // For record list, use the current page ID from request to stay on the same page
            $currentPageId = (int) ($request->getQueryParams()['id'] ?? 0);

            return [
                'id' => $currentPageId ?: $uid,
            ];
        }

        if (RouteUtility::isFileListRoute($route)) {
            // For file list, use the folder identifier from request to stay on the same folder
            return [
                'id' => $request->getQueryParams()['id'] ?? '',
            ];
        }

        return [
            'id' => $uid,
        ];
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
        foreach ($allStatus as $statusItem) {
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
