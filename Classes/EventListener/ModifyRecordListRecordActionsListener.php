<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyRecordListRecordActionsListener
{
    protected ServerRequest $request;
    public function __construct(private IconFactory $iconFactory, private UriBuilder $uriBuilder, protected StatusRepository $statusRepository)
    {
        $this->request = $GLOBALS['TYPO3_REQUEST'];
    }

    public function __invoke(ModifyRecordListRecordActionsEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }
        $table = $event->getTable();

        if (in_array($table, $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']) && !$event->hasAction('Status')) {
            $uid = $event->getRecord()['uid'];

            // ToDo: this is necessary cause the status is not in the record, pls check tca for this
            $record = ContentUtility::getExtensionRecord($table, $uid);
            if (!is_array($record)) {
                return;
            }

            $statusId = $record['tx_ximatypo3contentplanner_status'];
            $statusItem = StatusItem::create($record);
            $status = ContentUtility::getStatus($statusId);

            $title = $status ? $status->getTitle() : 'Status';
            $icon = $status ? $status->getColoredIcon() : 'flag-gray';
            $action = '<div class="btn-group" style="margin-left:10px;">
                <a href="#" class="btn btn-default dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="' . $title . '">'
                . $this->iconFactory->getIcon($icon, \TYPO3\CMS\Core\Imaging\IconSize::SMALL->value)->render() . '</a><ul class="dropdown-menu">';

            foreach ($this->statusRepository->findAll() as $statusEntry) {
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
                                'id' =>  $event->getRecord()['pid'],
                            ]
                        ),
                    ]
                );
                $action .= '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars($url) . '" title="' . $statusEntry->getTitle() . '">'
                    . $this->iconFactory->getIcon($statusEntry->getColoredIcon(), \TYPO3\CMS\Core\Imaging\IconSize::SMALL->value)->render() . $statusEntry->getTitle() . '</a></li>';
            }
            $action .= '<li><hr class="dropdown-divider"></li>';
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
                            'id' =>  $event->getRecord()['pid'],
                        ]
                    ),
                ]
            );
            $action .= '<li><a class="dropdown-item dropdown-item-spaced" href="' . htmlspecialchars($url) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset') . '">'
                . $this->iconFactory->getIcon('actions-close', \TYPO3\CMS\Core\Imaging\IconSize::SMALL->value)->render() . $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset') . '</a></li>';
            $action .= '</ul>';

            if ((bool)$record['tx_ximatypo3contentplanner_assignee']) {
                $action .= '
                <a class="btn btn-default" title="' . $title . '" href="' . $this->getEditUrl($table, $uid) . '">' . $statusItem->getAssigneeAvatar() . ' ' . $statusItem->getAssigneeName() . '</a>';
            }
            if ((bool)$record['tx_ximatypo3contentplanner_comments']) {
                $action .= '
                <a class="btn btn-default" title="' . $title . '" href="' . $this->getEditUrl($table, $uid) . '">' . $this->iconFactory->getIcon('content-message', \TYPO3\CMS\Core\Imaging\IconSize::SMALL->value)->render() . ' ' . $record['tx_ximatypo3contentplanner_comments'] . '</a>';
            }
            $action .= '</div>';
            $event->setAction(
                $action,
                'Status',
                'primary',
                '',
                'delete',
            );
        }
    }

    private function getEditUrl(string $table, int $uid): string
    {
        $params = [
            'edit' => [$table => [$uid => 'edit']],
            'returnUrl' => $this->request->getAttribute('normalizedParams')->getRequestUri(),
            'columnsOnly' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee,tx_ximatypo3contentplanner_comments',
        ];
        return (string)$this->uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
