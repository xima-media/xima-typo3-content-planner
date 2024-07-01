<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItem;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyButtonBarEventListener
{
    public function __construct(private IconFactory $iconFactory, private UriBuilder $uriBuilder)
    {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $pageId = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);

        $page = ContentUtility::getPage($pageId);
        $buttonBar = $event->getButtonBar();

        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $dropDownButton = $buttonBar->makeDropDownButton()
            ->setLabel('Dropdown')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status'))
            ->setIcon($this->iconFactory->getIcon(
                $page['tx_ximatypo3contentplanner_status'] ? Configuration::STATUS_ICONS[$page['tx_ximatypo3contentplanner_status']] : 'flag-gray'
            ))
        ;

        foreach (Configuration::STATUS_ICONS as $status => $icon) {
            $dropDownButton->addItem(
                GeneralUtility::makeInstance(DropDownItem::class)
                    ->setLabel($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status.' . $status))
                    ->setIcon($this->iconFactory->getIcon(Configuration::STATUS_ICONS[$status]))
                    ->setHref(
                        $this->uriBuilder->buildUriFromRoute(
                            'tce_db',
                            [
                                'data' => [
                                    'pages' => [
                                        $pageId => [
                                            'tx_ximatypo3contentplanner_status' => $status,
                                        ],
                                    ],
                                ],
                                'redirect' => (string)$this->uriBuilder->buildUriFromRoute(
                                    'web_layout',
                                    [
                                        'id' =>  $pageId,
                                    ]
                                ),
                            ]
                        )
                    )
            );
        }
        $dropDownButton->addItem(
            GeneralUtility::makeInstance(DropDownItem::class)
                ->setLabel($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'))
                ->setIcon($this->iconFactory->getIcon('actions-close'))
                ->setHref(
                    $this->uriBuilder->buildUriFromRoute(
                        'tce_db',
                        [
                            'data' => [
                                'pages' => [
                                    $pageId => [
                                        'tx_ximatypo3contentplanner_status' => '',
                                    ],
                                ],
                            ],
                            'redirect' => (string)$this->uriBuilder->buildUriFromRoute(
                                'web_layout',
                                [
                                    'id' =>  $pageId,
                                ]
                            ),
                        ]
                    )
                )
        );

        $buttons['right'][] = [$dropDownButton];
        $event->setButtons($buttons);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
