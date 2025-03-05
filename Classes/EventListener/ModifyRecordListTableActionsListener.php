<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListTableActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyRecordListTableActionsListener
{
    protected ServerRequest $request;

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly StatusRepository $statusRepository,
    ) {
        $this->request = $GLOBALS['TYPO3_REQUEST'];
    }

    public function __invoke(ModifyRecordListTableActionsEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        $table = $event->getTable();

        if (!ExtensionUtility::isRegisteredRecordTable($table) || $event->hasAction('Status')) {
            return;
        }

        $action = '<div class="btn-group" style="margin-left:10px;">
                <a href="#" class="btn btn-sm btn-default dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="test">' .
            $this->iconFactory->getIcon('flag-gray', Icon::SIZE_SMALL)->render() . '</a><ul class="dropdown-menu">';

        $actionsToAdd = [];

        foreach ($this->statusRepository->findAll() as $statusEntry) {
            $url = $this->buildUri($event, $statusEntry);
            $actionsToAdd[$statusEntry->getUid()] = '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars($url) . '" title="' . $statusEntry->getTitle() . '">'
                . $this->iconFactory->getIcon($statusEntry->getColoredIcon(), Icon::SIZE_SMALL)->render() . $statusEntry->getTitle() . '</a></li>';
        }
        $actionsToAdd['divider'] = '<li><hr class="dropdown-divider"></li>';

        // reset
        $url = $this->buildUri($event, null);
        $actionsToAdd['reset'] = '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars($url) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset') . '">'
            . $this->iconFactory->getIcon('actions-close', Icon::SIZE_SMALL)->render() . $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset') . '</a></li>';

        foreach ($actionsToAdd as $actionToAdd) {
            $action .= $actionToAdd;
        }
        $action .= '</ul>';
        $action .= '</div>';

        $event->setAction(
            $action,
            'Status',
            '',
            'edit',
        );
    }

    private function buildUri(ModifyRecordListTableActionsEvent $event, ?Status $statusEntry): \Psr\Http\Message\UriInterface
    {
        $dataArray = [
            $event->getTable() => [],

        ];
        $routeArray = [
            'id' => $event->getRecordList()->id,
        ];

        if ($event->getRecordList()->table !== '') {
            $routeArray['table'] = $event->getRecordList()->table;
        }
        foreach ($event->getRecordIds() as $recordId) {
            $dataArray[$event->getTable()][$recordId] = [
                'tx_ximatypo3contentplanner_status' => $statusEntry ? $statusEntry->getUid() : '',
            ];
        }

        return $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => $dataArray,
                'redirect' => (string)$this->uriBuilder->buildUriFromRoute('web_list', $routeArray),
            ],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
