<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Backend\ContextMenu\ItemProviders;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

class StatusItemProvider extends AbstractProvider
{
    public function __construct(private readonly  StatusRepository $statusRepository, private readonly RecordRepository $recordRepository, private readonly BackendUserRepository $backendUserRepository)
    {
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
        ];
    }

    public function addItems(array $items): array
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return $items;
        }

        $this->initDisabledItems();
        foreach ($this->statusRepository->findAll() as $statusItem) {
            $this->itemsConfiguration['wrap']['childItems'][$statusItem->getUid()] = [
                'label' => $statusItem->getTitle(),
                'iconIdentifier' => $statusItem->getColoredIcon(),
                'callbackAction' => 'change',
            ];
        }
        $this->itemsConfiguration['wrap']['childItems']['divider'] = ['type' => 'divider'];

        $this->itemsConfiguration['wrap']['childItems']['reset'] = [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:reset',
            'iconIdentifier' => 'actions-close',
            'callbackAction' => 'reset',
        ];

        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_EXTEND_CONTEXT_MENU)) {
            $record = $this->recordRepository->findByUid($this->table, (int)$this->identifier);
            if ($record) {
                $this->itemsConfiguration['wrap']['childItems']['divider2'] = ['type' => 'divider'];

                // remove current status from list
                if (in_array($record['tx_ximatypo3contentplanner_status'], array_keys($this->itemsConfiguration['wrap']['childItems']), true)) {
                    unset($this->itemsConfiguration['wrap']['childItems'][$record['tx_ximatypo3contentplanner_status']]);
                }

                // remove reset if status is already null
                if ($record['tx_ximatypo3contentplanner_status'] === null) {
                    unset($this->itemsConfiguration['wrap']['childItems']['reset']);
                }

                // assignee
                if ($record['tx_ximatypo3contentplanner_assignee']) {
                    $username = $this->backendUserRepository->getUsernameByUid($record['tx_ximatypo3contentplanner_assignee']);
                    $this->itemsConfiguration['wrap']['childItems']['assignee'] = [
                        'label' => $username,
                        'iconIdentifier' => 'actions-user',
                        'callbackAction' => 'load',
                    ];
                }

                // comments
                if ($record['tx_ximatypo3contentplanner_comments']) {
                    $this->itemsConfiguration['wrap']['childItems']['comments'] = [
                        'label' => $record['tx_ximatypo3contentplanner_comments'] . ' ' . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments'),
                        'iconIdentifier' => 'actions-message',
                        'callbackAction' => 'load',
                    ];
                }
            }
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
