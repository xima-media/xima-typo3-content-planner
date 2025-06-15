<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Manager;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

class StatusChangeManager
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository
    ) {
    }

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
            $previousStatus = $preRecord['tx_ximatypo3contentplanner_status'] ? ContentUtility::getStatus($preRecord['tx_ximatypo3contentplanner_status']) : null;
            $newStatus = $incomingFieldArray['tx_ximatypo3contentplanner_status'] ? ContentUtility::getStatus((int)$incomingFieldArray['tx_ximatypo3contentplanner_status']) : null;
            $this->eventDispatcher->dispatch(new StatusChangeEvent($table, $id, $incomingFieldArray, $previousStatus, $newStatus));

            if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] === null && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET)) {
                $this->clearStatusOfExtensionRecords('tt_content', pid: $id);
            }
        }
    }

    public function clearStatusOfExtensionRecords(string $table, ?int $status = null, ?int $pid = null): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
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

    private function nullableField(array &$incomingFieldArray, string $field): void
    {
        if (array_key_exists($field, $incomingFieldArray) && ($incomingFieldArray[$field] === '' || $incomingFieldArray[$field] === 0)) {
            $incomingFieldArray[$field] = null;
        }
    }

    private function isStatusFieldChanged(array $incomingFieldArray, array|bool $preRecord): bool
    {
        if (!is_array($preRecord)) {
            return false;
        }
        return $preRecord['tx_ximatypo3contentplanner_status'] !== $incomingFieldArray['tx_ximatypo3contentplanner_status'];
    }
}
