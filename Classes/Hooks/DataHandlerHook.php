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
            $id = array_key_first($datamap['tx_ximatypo3contentplanner_comment']);
            if (!MathUtility::canBeInterpretedAsInteger($id) && !array_key_exists('pages', $dataHandler->datamap) && $datamap['tx_ximatypo3contentplanner_comment'][$id]['foreign_table'] === 'pages') {
                $dataHandler->datamap['pages'][$datamap['tx_ximatypo3contentplanner_comment'][$id]['pid']]['tx_ximatypo3contentplanner_comments'] = $id;
                // Set author to current user
                $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['author'] = $GLOBALS['BE_USER']->getUserId();
            }
        }
    }

    public function clearCachePostProc(array $params): void
    {
        $this->cache->flushByTags(array_keys($params['tags']));
    }
}
