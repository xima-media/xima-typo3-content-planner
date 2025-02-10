<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Hooks;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
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

    public function clearCachePostProc(array $params): void
    {
        $this->cache->flushByTags(array_keys($params['tags']));
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
            $foreign_table = null;
            $foreign_uid = null;
            // @ToDo: Why are default values doesn't seem to be set as expected?
            foreach ($dataHandler->defaultValues['tx_ximatypo3contentplanner_comment'] as $key => $value) {
                if ($key === 'foreign_table') {
                    $foreign_table = $value;
                }
                if ($key === 'foreign_uid') {
                    $foreign_uid = $value;
                }
                $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id][$key] = $value;
            }

            if ($foreign_table === null || $foreign_uid === null) {
                return;
            }

            /*
            * Workaround to solve relation of comments created within the modal
            * This doesn't seem to be necessary in TYPO3 >= 13.0.0 (causes a strange bug with resetting the crdate)
            */
            if (version_compare(VersionNumberUtility::getCurrentTypo3Version(), '13.0.0', '<')) {
                $dataHandler->datamap[$foreign_table][$foreign_uid]['tx_ximatypo3contentplanner_comments'] = $id;
            }
        }
    }
}
