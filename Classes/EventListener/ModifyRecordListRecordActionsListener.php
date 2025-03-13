<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyRecordListRecordActionsListener
{
    protected ServerRequest $request;

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly StatusSelectionManager $statusSelectionManager,
        private readonly RequestId $requestId
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
        $statusItem = StatusItem::create($record);
        $status = $this->statusRepository->findByUid($statusId);

        $title = $status ? $status->getTitle() : 'Status';
        $icon = $status ? $status->getColoredIcon() : 'flag-gray';
        $action = '<div class="btn-group" style="margin-left:10px;">
                <a href="#" class="btn btn-default dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="' . $title . '">'
            . $this->iconFactory->getIcon($icon, Icon::SIZE_SMALL)->render() . '</a><ul class="dropdown-menu">';

        $actionsToAdd = [];

        foreach ($allStatus as $statusEntry) {
            $url = $this->uriBuilder->buildUriFromRoute(
                'tce_db',
                [
                    'data' => [
                        $table => [
                            $uid => [
                                'tx_ximatypo3contentplanner_status' => $statusEntry->getUid(),
                            ],
                        ],
                    ],
                    'redirect' => (string)$this->uriBuilder->buildUriFromRoute(
                        'web_list',
                        [
                            'id' => $event->getRecord()['pid'],
                        ]
                    ),
                ]
            );
            $actionsToAdd[$statusEntry->getUid()] = '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars($url) . '" title="' . $statusEntry->getTitle() . '">'
                . $this->iconFactory->getIcon($statusEntry->getColoredIcon(), Icon::SIZE_SMALL)->render() . $statusEntry->getTitle() . '</a></li>';
        }
        $actionsToAdd['divider'] = '<li><hr class="dropdown-divider"></li>';

        // reset
        $url = $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => [
                    $table => [
                        $uid => [
                            'tx_ximatypo3contentplanner_status' => '',
                        ],
                    ],
                ],
                'redirect' => (string)$this->uriBuilder->buildUriFromRoute(
                    'web_list',
                    [
                        'id' => $event->getRecord()['pid'],
                    ]
                ),
            ]
        );
        $actionsToAdd['reset'] = '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars($url) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset') . '">'
            . $this->iconFactory->getIcon('actions-close', Icon::SIZE_SMALL)->render() . $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset') . '</a></li>';

        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_EXTEND_CONTEXT_MENU)) {
            if ($record['tx_ximatypo3contentplanner_assignee'] || $record['tx_ximatypo3contentplanner_comments']) {
                $actionsToAdd['divider2'] = '<li><hr class="dropdown-divider"></li>';
            }

            // remove current status from list
            if (in_array($record['tx_ximatypo3contentplanner_status'], array_keys($actionsToAdd), true)) {
                unset($actionsToAdd[$record['tx_ximatypo3contentplanner_status']]);
            }

            // remove reset if status is already null
            if ($record['tx_ximatypo3contentplanner_status'] === null) {
                unset($actionsToAdd['divider']);
                unset($actionsToAdd['reset']);
            }

            // assignee
            if ($record['tx_ximatypo3contentplanner_assignee']) {
                $username = $this->backendUserRepository->getUsernameByUid($record['tx_ximatypo3contentplanner_assignee']);
                $actionsToAdd['assignee'] = '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid)) . '" title="' . $username . '">' . $statusItem->getAssigneeAvatar() . ' ' . $statusItem->getAssigneeName() . '</a></li>';
            }

            // comments
            if ($record['tx_ximatypo3contentplanner_status'] !== null) {
                $actionsToAdd['comments'] = '<li><a class="dropdown-item dropdown-item-spaced contentPlanner--comments" title="' . $title . '" href="#" data-force-ajax-url data-content-planner-comments data-table="' . $table . '" data-id="' . $uid . '" data-new-comment-uri="' . UrlHelper::getNewCommentUrl($table, $uid) . '">' . $this->iconFactory->getIcon('content-message', Icon::SIZE_SMALL)->render() . ' ' . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments') . ($record['tx_ximatypo3contentplanner_comments'] ? ' (' . $record['tx_ximatypo3contentplanner_comments'] . ')' : '') . '</a></li>';
                $actionsToAdd['comments'] .= ExtensionUtility::getJsTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/JavaScript/comments-modal.js', ['nonce' => $this->requestId->nonce]);
            }
        }

        $this->statusSelectionManager->prepareStatusSelection($this, $table, $uid, $actionsToAdd, $record['tx_ximatypo3contentplanner_status']);
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
