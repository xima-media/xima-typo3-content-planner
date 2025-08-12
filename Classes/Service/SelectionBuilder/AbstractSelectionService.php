<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
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
        private readonly CommentRepository $commentRepository,
        private readonly UriBuilder $uriBuilder
    ) {}

    /**
    * @return array<string, mixed>|bool
    * @throws NotImplementedException|Exception
    */
    public function generateSelection(string $table, int $uid): array|bool
    {
        if (!$this->shouldGenerateSelection($table)) {
            return false;
        }

        $allStatus = $this->statusRepository->findAll();
        if (count($allStatus) === 0) {
            return false;
        }

        $record = $this->getCurrentRecord($table, $uid);

        $selectionEntriesToAdd = [];
        foreach ($allStatus as $statusItem) {
            $this->addStatusItemToSelection($selectionEntriesToAdd, $statusItem, $this->getCurrentStatus($record), $table, $uid, $record);
        }

        if ($record === null || ($record['tx_ximatypo3contentplanner_status'] !== null && $record['tx_ximatypo3contentplanner_status'] !== 0)) {
            if ($selectionEntriesToAdd !== []) {
                $this->addDividerItemToSelection($selectionEntriesToAdd);
            }
            $this->addStatusResetItemToSelection($selectionEntriesToAdd, $table, $uid, $record);
        }

        if ($record !== null && $this->getCurrentStatus($record) !== null) {
            $this->addDividerItemToSelection($selectionEntriesToAdd, '2');
            $this->addAssigneeItemToSelection($selectionEntriesToAdd, $record, $table, $uid);
            $this->addCommentsItemToSelection($selectionEntriesToAdd, $record, $table, $uid);

            if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
                $this->addCommentsTodoItemToSelection($selectionEntriesToAdd, $record, $table, $uid);
            }
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

    /**
    * @return array<string, mixed>|bool|null
    * @throws Exception
    */
    protected function getCurrentRecord(string $table, int $uid): array|bool|null
    {
        return $this->recordRepository->findByUid($table, $uid, true);
    }

    /**
    * @param array<string, mixed>|bool|null $record
    */
    protected function getCurrentStatus(array|bool|null $record = null): int|null
    {
        return is_array($record) ? $record['tx_ximatypo3contentplanner_status'] : null;
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

    /**
    * @param array<int, int>|int $uid
    * @throws RouteNotFoundException
    */
    protected function buildUriForStatusChange(string $table, array|int $uid, ?Status $status, ?int $pid = null): UriInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $route = $request->getAttribute('routing')->getRoute()->getOption('_identifier'); // @phpstan-ignore-line

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
                'id' => $route === 'web_list' && (bool)$pid ? $pid : $uid,
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
                'tx_ximatypo3contentplanner_status' => $status instanceof Status ? $status->getUid() : '',
            ];
        }

        return $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => $dataArray,
                'redirect' => $this->uriBuilder->buildUriFromRoute(
                    'ximatypo3contentplanner_message',
                    [
                        'redirect' => (string)$this->uriBuilder->buildUriFromRoute($route, $routeArray),
                        'message' => $status instanceof Status ? 'status.changed' : 'status.reset',
                    ]
                ),
            ],
        );
    }

    /**
    * @param array<string, mixed> $record
    */
    protected function getCommentsTodoResolved(array $record, string $table): int
    {
        return isset($record['tx_ximatypo3contentplanner_comments']) && is_numeric($record['tx_ximatypo3contentplanner_comments']) && $record['tx_ximatypo3contentplanner_comments'] > 0 ? $this->commentRepository->countTodoAllByRecord($record['uid'], $table) : 0;
    }

    /**
    * @param array<string, mixed> $record
    */
    protected function getCommentsTodoTotal(array $record, string $table): int
    {
        return isset($record['tx_ximatypo3contentplanner_comments']) && is_numeric($record['tx_ximatypo3contentplanner_comments']) && $record['tx_ximatypo3contentplanner_comments'] > 0 ? $this->commentRepository->countTodoAllByRecord($record['uid'], $table, 'todo_total') : 0;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<int, int>|int|null $uid
    * @param array<string, mixed>|bool|null $record
    * @throws NotImplementedException
    */
    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960485);
    }

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<int, int>|int|null $uid
    * @param array<string, mixed>|bool|null $record
    * @throws NotImplementedException
    */
    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960486);
    }

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    * @throws NotImplementedException
    */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960487);
    }

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    * @throws NotImplementedException
    */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960488);
    }

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    * @throws NotImplementedException
    */
    public function addCommentsTodoItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960489);
    }

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @throws NotImplementedException
    */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        throw new NotImplementedException('Method not implemented', 1741960490);
    }
}
