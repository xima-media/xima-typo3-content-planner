<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

class AbstractSelectionService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly StatusSelectionManager $statusSelectionManager,
        private readonly UriBuilder $uriBuilder
    ) {
    }

    public function generateSelection(string $table, int $uid): array|bool
    {
        if (!$this->shouldGenerateSelection($table)) {
            return false;
        }

        $allStatus = $this->statusRepository->findAll();
        if (empty($allStatus)) {
            return false;
        }

        $record = $this->getCurrentRecord($table, $uid);

        $selectionEntriesToAdd = [];
        foreach ($allStatus as $statusItem) {
            $this->addStatusItemToSelection($selectionEntriesToAdd, $statusItem, $this->getCurrentStatus($record), $table, $uid, $record);
        }

        if ($record === null || ($record['tx_ximatypo3contentplanner_status'] !== null && $record['tx_ximatypo3contentplanner_status'] !== 0)) {
            if (!empty($selectionEntriesToAdd)) {
                $this->addDividerItemToSelection($selectionEntriesToAdd);
            }
            $this->addStatusResetItemToSelection($selectionEntriesToAdd, $table, $uid, $record);
        }

        if ($record !== null && $this->getCurrentStatus($record) !== null) {
            $this->addDividerItemToSelection($selectionEntriesToAdd, '2');
            $this->addAssigneeItemToSelection($selectionEntriesToAdd, $record, $table, $uid);
            $this->addCommentsItemToSelection($selectionEntriesToAdd, $record, $table, $uid);
        }

        $this->statusSelectionManager->prepareStatusSelection($this, $table, $uid, $selectionEntriesToAdd, $this->getCurrentStatus($record));
        return $selectionEntriesToAdd;
    }

    public function shouldGenerateSelection(string $table): bool
    {
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            return false;
        }

        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return false;
        }
        return true;
    }

    protected function getCurrentRecord(string $table, int $uid): array
    {
        return $this->recordRepository->findByUid($table, $uid);
    }

    protected function getCurrentStatus(array|null $record = null): int|null
    {
        return $record ? $record['tx_ximatypo3contentplanner_status'] : null;
    }

    protected function compareStatus(Status $status, Status|int|null $currentStatus): bool
    {
        if ($currentStatus === null) {
            return false;
        }

        if ($currentStatus instanceof Status) {
            return $status->getUid() === $currentStatus->getUid();
        }

        return $status->getUid() === $currentStatus;
    }

    protected function buildUri(string $table, array|int $uid, ?Status $status, ?int $pid = null): UriInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $route = $request->getAttribute('routing')->getRoute()->getOption('_identifier');

        if ($route === 'record_edit') {
            $routeArray = [
                'edit' => [
                    $table => [
                        $uid => 'edit',
                    ],
                ],
            ];
        } else {
            $routeArray = [
                'id' => $route === 'web_list' && $pid ? $pid : $uid,
            ];
        }

        $dataArray = [
            $table => [],
        ];
        if (is_int($uid)) {
            $uid = [$uid];
        }
        foreach ($uid as $singleId) {
            $dataArray[$table][$singleId] = [
                'tx_ximatypo3contentplanner_status' => $status ? $status->getUid() : '',
            ];
        }

        return $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => $dataArray,
                'redirect' => (string)$this->uriBuilder->buildUriFromRoute($route, $routeArray),
            ],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960485);
    }

    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960486);
    }

    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960487);
    }

    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960488);
    }

    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960489);
    }
}
