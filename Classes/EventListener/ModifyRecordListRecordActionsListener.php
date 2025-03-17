<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ListSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyRecordListRecordActionsListener
{
    protected ServerRequest $request;

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly RequestId $requestId,
        private readonly ListSelectionService $htmlSelectionService,
    ) {
        $this->request = $GLOBALS['TYPO3_REQUEST'];
    }

    public function __invoke(ModifyRecordListRecordActionsEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }
        $table = $event->getTable();

        if (!ExtensionUtility::isRegisteredRecordTable($table) || $event->hasAction('Status')) {
            return;
        }

        $allStatus = $this->statusRepository->findAll();
        if (empty($allStatus)) {
            return;
        }

        $uid = $event->getRecord()['uid'];

        // ToDo: this is necessary cause the status is not in the record, pls check tca for this
        $record = $this->recordRepository->findByUid($table, $uid);
        if (!is_array($record)) {
            return;
        }

        $statusId = $record['tx_ximatypo3contentplanner_status'];
        $status = $this->statusRepository->findByUid($statusId);

        $title = $status ? $status->getTitle() : 'Status';
        $icon = $status ? $status->getColoredIcon() : 'flag-gray';
        $action = '<div class="btn-group" style="margin-left:10px;">
                <a href="#" class="btn btn-default dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="' . $title . '">'
            . $this->iconFactory->getIcon($icon, Icon::SIZE_SMALL)->render() . '</a><ul class="dropdown-menu">';

        $actionsToAdd = $this->htmlSelectionService->generateSelection($table, $uid);
        foreach ($actionsToAdd as $actionToAdd) {
            $action .= $actionToAdd;
        }

        $action .= '</ul>';
        $action .= '</div>';
        $event->setAction(
            $action,
            'Status',
            'primary',
            '',
            'delete',
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
