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
    public function __construct(
        private FrontendInterface $cache,
        private readonly StatusChangeManager $statusChangeManager,
        private readonly RecordRepository $recordRepository
    ) {
    }

    /**
    * Hook: processDatamap_preProcessFieldArray
    */
    public function processDatamap_preProcessFieldArray(array &$incomingFieldArray, $table, $id, DataHandler $dataHandler): void
    {
        if (array_key_exists('tx_ximatypo3contentplanner_comment', $dataHandler->datamap)) {
            $this->updateCommentTodo($dataHandler);
            $this->checkCommentResolved($dataHandler);
        }

        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return;
        }

        if (ExtensionUtility::isRegisteredRecordTable($table)) {
            $this->statusChangeManager->processContentPlannerFields($incomingFieldArray, $table, $id);
        }

        if (array_key_exists('tx_ximatypo3contentplanner_comment', $dataHandler->datamap)) {
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
            foreach (ExtensionUtility::getRecordTables() as $recordTable) {
                $this->statusChangeManager->clearStatusOfExtensionRecords($recordTable, $id);
            }
        }
    }

    /**
    * Hook: processDatamap_beforeStart
    */
    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        if (array_key_first($dataHandler->datamap) === 'tx_ximatypo3contentplanner_comment') {
            $this->updateCommentTodo($dataHandler);
            $this->checkCommentResolved($dataHandler);

            // Workaround to solve relation of comments created within the modal
            $this->fixNewCommentEntry($dataHandler);
        }
    }

    /**
    * Hook: processDatamap_afterDatabaseOperations
    */
    public function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, DataHandler $dataHandler): void
    {
        if ($table === 'tx_ximatypo3contentplanner_comment') {
            /*
            * This is a workaround to update the relation of comments to the content planner record.
            * The relation is not updated correctly by the DataHandler.
            * The following code example from the official documentation does not work as expected:
            * dataHandler->datamap[$foreign_table][$foreign_uid]['tx_ximatypo3contentplanner_comments'] = $newCommentUid;
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
            $tags[] = $params['table'] . '__pageId__' . $params['uid_page'];
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

    private function updateCommentTodo($dataHandler): void
    {
        foreach (array_keys($dataHandler->datamap['tx_ximatypo3contentplanner_comment']) as $id) {
            if (!array_key_exists('content', $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id])) {
                continue;
            }

            $content = $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['content'];
            if (empty($content)) {
                continue;
            }

            $todos = $this->parseTodos($content);
            $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['todo_total'] = $todos['total'];
            $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['todo_resolved'] = $todos['resolved'];
        }
    }

    private function checkCommentResolved($dataHandler): void
    {
        foreach (array_keys($dataHandler->datamap['tx_ximatypo3contentplanner_comment']) as $id) {
            if (array_key_exists('resolved', $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]) && $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['resolved'] !== '') {
                $dataHandler->datamap['tx_ximatypo3contentplanner_comment'][$id]['resolved'] = json_encode([
                    'user' => $GLOBALS['BE_USER']->user['uid'],
                    'date' => time(),
                ]);
            }
        }
    }

    private function parseTodos(string $htmlContent): array
    {
        $dom = new \DOMDocument();
        // Use libxml error handling instead of @ suppression
        $previousLibXmlUseErrors = \libxml_use_internal_errors(true);
        $success = $dom->loadHTML($htmlContent);
        \libxml_use_internal_errors($previousLibXmlUseErrors);

        $todos = ['total' => 0, 'resolved' => 0, 'pending' => 0];
        if (!$success) {
            return $todos;
        }

        foreach ($dom->getElementsByTagName('input') as $checkbox) {
            if ($checkbox->getAttribute('type') === 'checkbox') {
                $todos['total']++;
                $checkbox->hasAttribute('checked') ? $todos['resolved']++ : $todos['pending']++;
            }
        }

        return $todos;
    }
}
