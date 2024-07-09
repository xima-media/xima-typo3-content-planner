<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Hooks;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

final class DataHandlerHook
{
    /**
     * Hook: processDatamap_preProcessFieldArray
     *
     * @param array $incomingFieldArray The array of fields to be processed
     * @param string $table The table the data should be saved to
     * @param int $id The ID of the record
     * @param DataHandler $dataHandler Reference to the DataHandler object
     */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, $table, $id, DataHandler &$dataHandler)
    {
        if ($table === 'pages') {
            if (!MathUtility::canBeInterpretedAsInteger($id)) {
                return;
            }
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
            if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] === null && $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['pages']['autoAssignment']) {
                $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = null;
            }

            // auto assign user if status is set
            if ($incomingFieldArray['tx_ximatypo3contentplanner_status'] !== null && $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] === null && $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['pages']['autoUnassignment']) {
                $incomingFieldArray['tx_ximatypo3contentplanner_assignee'] = $GLOBALS['BE_USER']->getUserId();
            }
        }
    }
}
