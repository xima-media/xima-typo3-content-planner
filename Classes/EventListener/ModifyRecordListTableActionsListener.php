<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListTableActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, IconHelper, VisibilityUtility};

use function count;

/**
 * ModifyRecordListTableActionsListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
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

        $allStatus = $this->statusRepository->findAll();
        if (0 === count($allStatus)) {
            return;
        }

        $action = '<div class="btn-group" style="margin-left:10px;">
                <a href="#" class="btn btn-sm btn-default dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="test">'.
            $this->iconFactory->getIcon('flag-gray', IconHelper::getDefaultIconSize())->render().'</a><ul class="dropdown-menu">';

        $actionsToAdd = [];

        foreach ($allStatus as $statusEntry) {
            $url = $this->buildUri($event, $statusEntry);
            $actionsToAdd[$statusEntry->getUid()] = '<li><a class="dropdown-item dropdown-item-spaced" href="'.htmlspecialchars((string) $url, \ENT_QUOTES | \ENT_HTML5).'" title="'.$statusEntry->getTitle().'">'
                .$this->iconFactory->getIcon($statusEntry->getColoredIcon(), IconHelper::getDefaultIconSize())->render().$statusEntry->getTitle().'</a></li>';
        }
        $actionsToAdd['divider'] = '<li><hr class="dropdown-divider"></li>';

        // reset
        $url = $this->buildUri($event, null);
        $actionsToAdd['reset'] = '<li><a class="dropdown-item dropdown-item-spaced" href="'.htmlspecialchars((string) $url, \ENT_QUOTES | \ENT_HTML5).'" title="'.$this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset').'">'
            .$this->iconFactory->getIcon('actions-close', IconHelper::getDefaultIconSize())->render().$this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset').'</a></li>';

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

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function buildUri(ModifyRecordListTableActionsEvent $event, ?Status $statusEntry): UriInterface
    {
        $dataArray = [
            $event->getTable() => [],
        ];
        $routeArray = [
            'id' => $event->getRecordList()->id,
        ];

        if ('' !== $event->getRecordList()->table) {
            $routeArray['table'] = $event->getRecordList()->table;
        }
        foreach ($event->getRecordIds() as $recordId) {
            $dataArray[$event->getTable()][$recordId] = [
                'tx_ximatypo3contentplanner_status' => $statusEntry instanceof Status ? $statusEntry->getUid() : '',
            ];
        }

        return $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => $dataArray,
                'redirect' => (string) $this->uriBuilder->buildUriFromRoute('web_list', $routeArray),
            ],
        );
    }
}
