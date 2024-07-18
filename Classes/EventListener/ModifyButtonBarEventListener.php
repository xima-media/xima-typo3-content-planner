<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownDivider;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItem;
use TYPO3\CMS\Backend\Template\Components\Buttons\InputButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyButtonBarEventListener
{
    public function __construct(private IconFactory $iconFactory, private UriBuilder $uriBuilder, protected StatusRepository $statusRepository)
    {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        if (isset($request->getQueryParams()['edit'])) {
            $table = array_key_first($request->getQueryParams()['edit']);
        } elseif (isset($request->getQueryParams()['id'])) {
            $table = 'pages';
        } else {
            return;
        }

        if ($table === 'tx_ximatypo3contentplanner_comment') {
            $this->removeButtonsExceptSave($event);
            return;
        }
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            return;
        }

        if ($table === 'pages') {
            $uid = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? (isset($request->getQueryParams()['edit']['pages']) ? array_keys($request->getQueryParams()['edit']['pages'])[0] : 0));
            $page = ContentUtility::getPage($uid);
            $status = $page['tx_ximatypo3contentplanner_status'] ? ContentUtility::getStatus($page['tx_ximatypo3contentplanner_status']) : null;
        } else {
            $uid = (int)array_key_first($request->getQueryParams()['edit'][$table]);
            $record = ContentUtility::getExtensionRecord($table, $uid);
            if (!$record) {
                return;
            }
            $status = $record['tx_ximatypo3contentplanner_status'] ? ContentUtility::getStatus($record['tx_ximatypo3contentplanner_status']) : null;
        }
        $buttonBar = $event->getButtonBar();
        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $dropDownButton = $buttonBar->makeDropDownButton()
            ->setLabel('Dropdown')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status'))
            ->setIcon($this->iconFactory->getIcon(
                $status ? $status->getColoredIcon() : 'flag-gray'
            ));

        foreach ($this->statusRepository->findAll() as $statusItem) {
            $dropDownButton->addItem(
                GeneralUtility::makeInstance(DropDownItem::class)
                    ->setLabel($statusItem->getTitle())
                    ->setIcon($this->iconFactory->getIcon($statusItem->getColoredIcon()))
                    ->setHref(
                        $this->uriBuilder->buildUriFromRoute(
                            'tce_db',
                            [
                                'data' => [
                                    $table => [
                                        $uid => [
                                            'tx_ximatypo3contentplanner_status' => $statusItem->getUid(),
                                        ],
                                    ],
                                ],
                                'redirect' => $table === 'pages' ?
                                    (string)$this->uriBuilder->buildUriFromRoute(
                                        'web_layout',
                                        [
                                            'id' => $uid,
                                        ]
                                    ) :
                                    (string)$this->uriBuilder->buildUriFromRoute(
                                        'record_edit',
                                        [
                                            'edit' => [
                                                $table => [
                                                    $uid => 'edit',
                                                ],
                                            ],
                                        ]
                                    ),
                            ]
                        )
                    )
            );
        }
        $dropDownButton->addItem(GeneralUtility::makeInstance(DropDownDivider::class));
        $dropDownButton->addItem(
            GeneralUtility::makeInstance(DropDownItem::class)
                ->setLabel($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'))
                ->setIcon($this->iconFactory->getIcon('actions-close'))
                ->setHref(
                    $this->uriBuilder->buildUriFromRoute(
                        'tce_db',
                        [
                            'data' => [
                                $table => [
                                    $uid => [
                                        'tx_ximatypo3contentplanner_status' => '',
                                    ],
                                ],
                            ],
                            'redirect' => $table === 'pages' ?
                                (string)$this->uriBuilder->buildUriFromRoute(
                                    'web_layout',
                                    [
                                        'id' => $uid,
                                    ]
                                ) :
                                (string)$this->uriBuilder->buildUriFromRoute(
                                    'record_edit',
                                    [
                                        'edit' => [
                                            $table => [
                                                $uid => 'edit',
                                            ],
                                        ],
                                    ]
                                ),
                        ]
                    )
                )
        );

        $buttons['right'][] = [$dropDownButton];
        $event->setButtons($buttons);
    }

    private function removeButtonsExceptSave(ModifyButtonBarEvent $event): void
    {
        $buttons = [];

        foreach ($event->getButtons() as $position => $buttonGroup) {
            if ($position === 'right') {
                continue;
            }
            foreach ($buttonGroup as $button) {
                if ($button[0] instanceof InputButton && str_contains($button[0]->getName(), '_save')) {
                    $buttons[$position][] = $button;
                }
            }
        }
        $event->setButtons($buttons);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
