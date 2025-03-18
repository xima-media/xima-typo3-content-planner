<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Hooks;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusChangeManager;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

final class DataHandlerHook
{
    public function __construct(private FrontendInterface $cache, private readonly StatusChangeManager $statusChangeManager, private readonly RecordRepository $recordRepository)
    {
    }

    /**
    * Hook: processDatamap_preProcessFieldArray
    */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, $table, $id, DataHandler &$dataHandler): void
    {
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        if (ExtensionUtility::isRegisteredRecordTable($table)) {
            $this->statusChangeManager->processContentPlannerFields($incomingFieldArray, $table, $id);
        }

        if (in_array('tx_ximatypo3contentplanner_comment', array_keys($dataHandler->datamap))) {
            $this->fixNewCommentEntry($dataHandler);
        }
    }

    /**
    * Hook: processCmdmap_preProcess
    */
    public function processCmdmap_preProcess($command, $table, $id, $value, DataHandler $parentObject, $pasteUpdate): void
    {
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }
        if ($command === 'delete' && $table === 'tx_ximatypo3contentplanner_status') {
            // Clear all status of records that are assigned to the deleted status
            foreach (ExtensionUtility::getRecordTables() as $table) {
                $this->statusChangeManager->clearStatusOfExtensionRecords($table, $id);
            }
        }
    }

    /**
    * Hook: processDatamap_beforeStart
    */
    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        $datamap = $dataHandler->datamap;
        // Workaround to solve relation of comments created within the modal
        if (array_key_first($datamap) === 'tx_ximatypo3contentplanner_comment') {
            $this->fixNewCommentEntry($dataHandler);
        }
    }

    /**
    * Hook: processDatamap_afterDatabaseOperations
    */
    public function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, DataHandler &$dataHandler): void
    {
        if ($table === 'tx_ximatypo3contentplanner_comment') {
            /*
            * This is a workaround to update the relation of comments to the content planner record.
            * The relation is not updated correctly by the DataHandler.
            * The following code example from the official documentation does not work as expected:
            * dataHandler->datamap[$foreign_table][$foreign_uid]['tx_ximatypo3contentplanner_comments'] = $newCommentUid;
            * (also the crdate will be overwritten)
            * Therefore we have to update the relation manually.
            */
            if (array_key_exists('foreign_table', $fieldArray) && array_key_exists('foreign_uid', $fieldArray)) {
                $this->recordRepository->updateCommentsRelationByRecord($fieldArray['foreign_table'], (int)$fieldArray['foreign_uid']);
            }
        }
    }

    public function clearCachePostProc(array $params): void
    {
        $tags = array_keys($params['tags']);
        if (in_array('uid_page', $params) && in_array('table', $params)) {
            $tags[] = $params['table'] . '__pages__' . $params['uid_page'];
        }
        $this->cache->flushByTags($tags);
    }

    private function fixNewCommentEntry(&$dataHandler): void
    {
        $id = null;
        foreach (array_keys($dataHandler->datamap['tx_ximatypo3contentplanner_comment']) as $key) {
            if (!MathUtility::canBeInterpretedAsInteger($key)) {
                $id = $key;
            }
        }

        if ($id === null) {
            return;
        }
        $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['author'] = $GLOBALS['BE_USER']->getUserId();

        if (array_key_exists('tx_ximatypo3contentplanner_comment', $dataHandler->defaultValues)) {
            // @ToDo: Why are default values doesn't seem to be set as expected?
            foreach ($dataHandler->defaultValues['tx_ximatypo3contentplanner_comment'] as $key => $value) {
                $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id][$key] = $value;
            }
        }
    }
}
