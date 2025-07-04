<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class RenderAdditionalContentToRecordListListener
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository
    ) {
    }

    public function __invoke(RenderAdditionalContentToRecordListEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_RECORD_LIST_STATUS_INFO)) {
            return;
        }

        $request = $event->getRequest();

        if (!array_key_exists('id', $request->getQueryParams())) {
            return;
        }
        $pid = (int)$request->getQueryParams()['id'];
        $table = array_key_exists('table', $request->getQueryParams()) ? $request->getQueryParams()['table'] : null;
        $records = [];

        if ($table !== null) {
            if (!ExtensionUtility::isRegisteredRecordTable($table)) {
                return;
            }

            $records[$table] = $this->recordRepository->findByPid($table, $pid, ignoreVisibilityRestriction: true);
        } else {
            foreach (ExtensionUtility::getRecordTables() as $recordTable) {
                $records[$recordTable] = $this->recordRepository->findByPid($recordTable, $pid, ignoreVisibilityRestriction: true);
            }
        }

        $additionalCss = '';

        foreach ($records as $tableName => $tableRecords) {
            if ($tableRecords === []) {
                continue;
            }
            foreach ($tableRecords as $tableRecord) {
                $status = $this->statusRepository->findByUid($tableRecord['tx_ximatypo3contentplanner_status']);
                if ($status instanceof Status) {
                    $additionalCss .= 'tr[data-table="' . $tableName . '"][data-uid="' . $tableRecord['uid'] . '"] > td { background-color: ' . Configuration\Colors::get($status->getColor(), true) . '; } ';
                }
            }
        }

        $event->addContentAbove("<style>$additionalCss</style>");
    }
}
