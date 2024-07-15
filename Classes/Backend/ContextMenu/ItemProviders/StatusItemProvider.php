<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Backend\ContextMenu\ItemProviders;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

class StatusItemProvider extends AbstractProvider
{
    public function __construct(protected StatusRepository $statusRepository)
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
        return $this->table === 'pages';
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
        $this->itemsConfiguration['wrap']['childItems']['divider'] = ['type' => 'divider',];

        $this->itemsConfiguration['wrap']['childItems']['reset'] = [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:reset',
            'iconIdentifier' => 'actions-close',
            'callbackAction' => 'reset',
        ];

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
}
