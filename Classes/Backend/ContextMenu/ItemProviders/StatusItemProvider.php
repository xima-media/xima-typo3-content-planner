<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Backend\ContextMenu\ItemProviders;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

class StatusItemProvider extends AbstractProvider
{
    public function __construct(
        private readonly  StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly StatusSelectionManager $statusSelectionManager
    ) {
        parent::__construct();
    }

    protected $itemsConfiguration = [
        'wrap' => [
            'type' => 'submenu',
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:status',
            'iconIdentifier' => 'flag-gray',
            'childItems' => [],
        ],
    ];

    public function canHandle(): bool
    {
        return ExtensionUtility::isRegisteredRecordTable($this->table) && $this->identifier;
    }

    public function getPriority(): int
    {
        return 55;
    }

    protected function getAdditionalAttributes(string|int $itemName): array
    {
        return [
            'data-callback-module' => '@xima/ximatypo3contentplanner/context-menu-actions',
            'data-status' => $itemName,
            'data-uri' => UrlHelper::getContentStatusPropertiesEditUrl($this->table, (int)$this->identifier, false),
            'data-new-comment-uri' => UrlHelper::getNewCommentUrl($this->table, (int)$this->identifier),
        ];
    }

    public function addItems(array $items): array
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return $items;
        }
        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/new-comment-modal.js');
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-modal.js');
        $pageRenderer->addInlineLanguageLabelFile('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf');

        $this->initDisabledItems();
        $itemsToAdd = [];
        foreach ($this->statusRepository->findAll() as $statusItem) {
            $itemsToAdd[$statusItem->getUid()] = [
                'label' => $statusItem->getTitle(),
                'iconIdentifier' => $statusItem->getColoredIcon(),
                'callbackAction' => 'change',
            ];
        }
        $itemsToAdd['divider'] = ['type' => 'divider'];

        $itemsToAdd['reset'] = [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:reset',
            'iconIdentifier' => 'actions-close',
            'callbackAction' => 'reset',
        ];

        $record = null;
        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_EXTEND_CONTEXT_MENU)) {
            $record = $this->recordRepository->findByUid($this->table, (int)$this->identifier);
            if ($record) {
                $itemsToAdd['divider2'] = ['type' => 'divider'];

                // remove current status from list
                if (in_array($record['tx_ximatypo3contentplanner_status'], array_keys($itemsToAdd), true)) {
                    unset($itemsToAdd[$record['tx_ximatypo3contentplanner_status']]);
                }

                // remove reset if status is already null
                if ($record['tx_ximatypo3contentplanner_status'] === null || $record['tx_ximatypo3contentplanner_status'] === 0) {
                    unset($itemsToAdd['reset']);
                }

                // assignee
                if ($record['tx_ximatypo3contentplanner_assignee']) {
                    $username = $this->backendUserRepository->getUsernameByUid($record['tx_ximatypo3contentplanner_assignee']);
                    $itemsToAdd['assignee'] = [
                        'label' => $username,
                        'iconIdentifier' => 'actions-user',
                        'callbackAction' => 'load',
                    ];
                }

                // comments
                if ($record['tx_ximatypo3contentplanner_status'] !== null && $record['tx_ximatypo3contentplanner_status'] !== 0) {
                    $itemsToAdd['comments'] = [
                        'label' => $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments') . ($record['tx_ximatypo3contentplanner_comments'] ? ' (' . $record['tx_ximatypo3contentplanner_comments'] . ')' : ''),
                        'iconIdentifier' => 'actions-message',
                        'callbackAction' => 'comments',
                    ];
                }
            }
        }

        $this->statusSelectionManager->prepareStatusSelection($this, $this->table, (int)$this->identifier, $itemsToAdd, $record ? $record['tx_ximatypo3contentplanner_status'] : null);
        foreach ($itemsToAdd as $itemKey => $itemToAdd) {
            $this->itemsConfiguration['wrap']['childItems'][$itemKey] = $itemToAdd;
        }

        $localItems = $this->prepareItems($this->itemsConfiguration);

        if (isset($items['info'])) {
            $position = array_search('info', array_keys($items), true);
            $beginning = array_slice($items, 0, $position + 1, true);
            $end = array_slice($items, $position, null, true);

            $items = $beginning + $localItems + $end;
        } else {
            $items = $items + $localItems;
        }
        return $items;
    }

    protected function canRender(string|int $itemName, string $type): bool
    {
        if (in_array($itemName, $this->disabledItems, true)) {
            return false;
        }
        return true;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
