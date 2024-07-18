<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Hooks;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

final class DataHandlerHook
{
    /**
     * Hook: processDatamap_preProcessFieldArray
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, $table, $id, DataHandler &$dataHandler): void
    {
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        if (ExtensionUtility::isRegisteredRecordTable($table)) {
            $this->processContentPlannerFields($incomingFieldArray, $table, $id);
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
                ContentUtility::clearStatusOfExtensionRecords($table, $id);
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
            $id = array_key_first($datamap['tx_ximatypo3contentplanner_comment']);
            if (!MathUtility::canBeInterpretedAsInteger($id) && !array_key_exists('pages', $dataHandler->datamap)) {
                $dataHandler->datamap['pages'][$datamap['tx_ximatypo3contentplanner_comment'][$id]['pid']]['tx_ximatypo3contentplanner_comments'] = $id;
            }
        }
    }

    private function processContentPlannerFields(array &$incomingFieldArray, $table, $id): void
    {
        if (!isset($incomingFieldArray['tx_ximatypo3contentplanner_status'])) {
            return;
        }
        if ($incomingFieldArray['tx_ximatypo3contentplanner_assignee'] === '' || $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] === 0) {
            $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = null;
        }

        if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] === '' || $incomingFieldArray['tx_ximatypo3contentplanner_status'] === 0) {
            $incomingFieldArray['tx_ximatypo3contentplanner_status'] = null;
        }

        // auto reset assignee if status is set to null
        if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] === null && $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['features']['autoAssignment']) {
            $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = null;
        }

        // auto assign user if status is initially set
        if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] !== null && $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] === null && $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['features']['autoUnassignment']) {
            $preRecord = ContentUtility::getExtensionRecord($table, $id);
            if ($preRecord['tx_ximatypo3contentplanner_status'] === null || $preRecord['tx_ximatypo3contentplanner_status'] === 0) {
                $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = $GLOBALS['BE_USER']->getUserId();
            }
        }
    }
}
