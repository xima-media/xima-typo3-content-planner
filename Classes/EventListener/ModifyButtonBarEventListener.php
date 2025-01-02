<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownDivider;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItem;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\InputButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyButtonBarEventListener
{
    public function __construct(private readonly IconFactory $iconFactory, private readonly UriBuilder $uriBuilder, private readonly StatusRepository $statusRepository, private readonly RecordRepository $recordRepository, private readonly BackendUserRepository $backendUserRepository)
    {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        if ($request->getAttribute('module') &&
            !in_array($request->getAttribute('module')->getIdentifier(), ['web_layout', 'record_edit', 'web_list'])) {
            return;
        }

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
            $record = ContentUtility::getPage($uid);
        } else {
            $uid = (int)array_key_first($request->getQueryParams()['edit'][$table]);
            $record = $this->recordRepository->findByUid($table, $uid);
        }
        if (!$record) {
            return;
        }
        $status = $record['tx_ximatypo3contentplanner_status'] ? $this->statusRepository->findByUid($record['tx_ximatypo3contentplanner_status']) : null;

        $buttonBar = $event->getButtonBar();
        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $dropDownButton = $buttonBar->makeDropDownButton()
            ->setLabel('Dropdown')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status'))
            ->setIcon($this->iconFactory->getIcon(
                $status ? $status->getColoredIcon() : 'flag-gray'
            ));

        $buttonsToAdd = [];
        foreach ($this->statusRepository->findAll() as $statusItem) {
            /** @var DropDownItemInterface $statusDropDownItem */
            $statusDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
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
                );
            $buttonsToAdd[$statusItem->getUid()] = $statusDropDownItem;
        }
        $buttonsToAdd['divider'] = GeneralUtility::makeInstance(DropDownDivider::class);

        /** @var DropDownItemInterface $statusDropDownItem */
        $statusDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
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
            );
        $buttonsToAdd['reset'] = $statusDropDownItem;

        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_EXTEND_CONTEXT_MENU)) {
            if ($record['tx_ximatypo3contentplanner_assignee'] || $record['tx_ximatypo3contentplanner_comments']) {
                $buttonsToAdd['divider2'] = GeneralUtility::makeInstance(DropDownDivider::class);
            }

            // remove current status from list
            if (in_array($record['tx_ximatypo3contentplanner_status'], array_keys($buttonsToAdd), true)) {
                unset($buttonsToAdd[$record['tx_ximatypo3contentplanner_status']]);
            }

            // remove reset if status is already null
            if ($record['tx_ximatypo3contentplanner_status'] === null) {
                unset($buttonsToAdd['divider']);
                unset($buttonsToAdd['reset']);
            }

            // assignee
            if ($record['tx_ximatypo3contentplanner_assignee']) {
                $username = $this->backendUserRepository->getUsernameByUid($record['tx_ximatypo3contentplanner_assignee']);
                $assigneeDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
                    ->setLabel($username)
                    ->setIcon($this->iconFactory->getIcon('actions-user'))
                    ->setHref(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid));
                $buttonsToAdd['assignee'] = $assigneeDropDownItem;
            }

            // comments
            if ($record['tx_ximatypo3contentplanner_comments']) {
                $commentsDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
                    ->setLabel($record['tx_ximatypo3contentplanner_comments'] . ' ' . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments'))
                    ->setIcon($this->iconFactory->getIcon('actions-message'))
                    ->setHref(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid));
                $buttonsToAdd['comments'] = $commentsDropDownItem;
            }
        }

        foreach ($buttonsToAdd as $buttonToAdd) {
            $dropDownButton->addItem($buttonToAdd);
        }

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
