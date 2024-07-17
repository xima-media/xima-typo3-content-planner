<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class RenderAdditionalContentToRecordListListener
{
    public function __invoke(RenderAdditionalContentToRecordListEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }
        $request = $event->getRequest();
        $pid = (int)$request->getQueryParams()['id'];
        $table = array_key_exists('table', $request->getQueryParams()) ? $request->getQueryParams()['table'] : null;
        $records = [];

        if ($table) {
            if (!ExtensionUtility::isRegisteredRecordTable($table)) {
                return;
            }

            $records[$table] = ContentUtility::getExtensionRecords($table, $pid);
        } else {
            foreach (ExtensionUtility::getRecordTables() as $table) {
                $records[$table] = ContentUtility::getExtensionRecords($table, $pid);
            }
        }

        $additionalCss = '';

        foreach ($records as $table => $tableRecords) {
            if (empty($tableRecords)) {
                continue;
            }
            foreach ($tableRecords as $tableRecord) {
                $status = ContentUtility::getStatus($tableRecord['tx_ximatypo3contentplanner_status']);
                if ($status) {
                    $additionalCss .= 'tr[data-table="' . $table . '"][data-uid="' . $tableRecord['uid'] . '"] > td { background-color: ' . Configuration::STATUS_COLOR_CODES[$status->getColor()] . '; } ';
                }
            }
        }

        $event->addContentAbove("<style>$additionalCss</style>");
    }
}
