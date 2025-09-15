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

namespace Xima\XimaTypo3ContentPlanner\Manager;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

/**
 * StatusChangeManager.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class StatusChangeManager
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
    * @param array<string, mixed> $incomingFieldArray
    * @param string $table
    * @param int $id
    * @throws Exception
    */
    public function processContentPlannerFields(array &$incomingFieldArray, $table, $id): void
    {
        if (!isset($incomingFieldArray['tx_ximatypo3contentplanner_status'])) {
            return;
        }

        $this->nullableField($incomingFieldArray, 'tx_ximatypo3contentplanner_assignee');
        $this->nullableField($incomingFieldArray, 'tx_ximatypo3contentplanner_status');

        // auto reset assignee if status is set to null
        if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] === null) {
            $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = null;

            if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET)) {
                $this->commentRepository->deleteAllCommentsByRecord($id, $table);
            }
        }

        $preRecord = $this->recordRepository->findByUid($table, $id);

        if ($preRecord === false) {
            return;
        }

        // auto assign user if status is initially set
        if ((!array_key_exists('tx_ximatypo3contentplanner_assignee', $incomingFieldArray) || $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] === null)
            && $incomingFieldArray['tx_ximatypo3contentplanner_status'] !== null
            && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_AUTO_ASSIGN)) {
            // Check if status was null before
            // ToDo: Check if this is the correct way to get the previous status
            if ($preRecord['tx_ximatypo3contentplanner_status'] === null
                || $preRecord['tx_ximatypo3contentplanner_status'] === 0) {
                $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = $GLOBALS['BE_USER']->getUserId();
            }
        }

        if ($this->isStatusFieldChanged($incomingFieldArray, $preRecord)) {
            $previousStatus = isset($preRecord['tx_ximatypo3contentplanner_status']) && is_numeric($preRecord['tx_ximatypo3contentplanner_status']) && $preRecord['tx_ximatypo3contentplanner_status'] > 0 ? ContentUtility::getStatus($preRecord['tx_ximatypo3contentplanner_status']) : null;
            $newStatus = isset($incomingFieldArray['tx_ximatypo3contentplanner_status']) && is_numeric($incomingFieldArray['tx_ximatypo3contentplanner_status']) && $incomingFieldArray['tx_ximatypo3contentplanner_status'] > 0 ? ContentUtility::getStatus((int)$incomingFieldArray['tx_ximatypo3contentplanner_status']) : null;
            $this->eventDispatcher->dispatch(new StatusChangeEvent($table, $id, $incomingFieldArray, $previousStatus, $newStatus));

            if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] === null && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET)) {
                $this->clearStatusOfExtensionRecords('tt_content', pid: $id);
            }
        }
    }

    public function clearStatusOfExtensionRecords(string $table, ?int $status = null, ?int $pid = null): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set('tx_ximatypo3contentplanner_status', null)
        ;

        if ((bool)$status) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('tx_ximatypo3contentplanner_status', $status)
            );
        }

        if ((bool)$pid) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('pid', $pid)
            );
        }

        $queryBuilder->executeQuery();
    }

    /**
    * @param array<string, mixed> $incomingFieldArray
    */
    private function nullableField(array &$incomingFieldArray, string $field): void
    {
        if (array_key_exists($field, $incomingFieldArray) && ($incomingFieldArray[$field] === '' || $incomingFieldArray[$field] === 0)) {
            $incomingFieldArray[$field] = null;
        }
    }

    /**
    * @param array<string, mixed> $incomingFieldArray
    * @param array<string, mixed>|bool $preRecord
    */
    private function isStatusFieldChanged(array $incomingFieldArray, array|bool $preRecord): bool
    {
        if (!is_array($preRecord)) {
            return false;
        }
        return $preRecord['tx_ximatypo3contentplanner_status'] !== $incomingFieldArray['tx_ximatypo3contentplanner_status'];
    }
}
