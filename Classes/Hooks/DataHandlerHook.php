<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Hooks;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3ContentPlanner\Manager\StatusChangeManager;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

final class DataHandlerHook
{
    public function __construct(private FrontendInterface $cache, private readonly StatusChangeManager $statusChangeManager)
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

        if (in_array('tx_ximatypo3contentplanner_comment', $dataHandler->datamap)) {
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

    public function clearCachePostProc(array $params): void
    {
        $this->cache->flushByTags(array_keys($params['tags']));
    }

    private function fixNewCommentEntry(&$dataHandler) {
        $id = null;
        foreach (array_keys($dataHandler->datamap['tx_ximatypo3contentplanner_comment']) as $key) {
            if (!MathUtility::canBeInterpretedAsInteger($key)) {
                $id = $key;
            }
        }

        if (array_key_exists('tx_ximatypo3contentplanner_comment', $dataHandler->defaultValues) && $id !== null) {
            $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['author'] = $GLOBALS['BE_USER']->getUserId();
            $table = null;
            // @ToDo: Why are default values doesn't seem to be set as expected?
            foreach ($dataHandler->defaultValues['tx_ximatypo3contentplanner_comment'] as $key => $value) {
                if ($key === 'foreign_table') {
                    $table = $value;
                }
                $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id][$key] = $value;
            }

            // @ToDo: how to fix this for other tables?
            if ($table === 'pages') {
                $dataHandler->datamap[$table][$dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['pid']]['tx_ximatypo3contentplanner_comments'] = $id;
            }
        }
    }
}
