<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Backend\ContextMenu\ItemProviders;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\PageTreeSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

class StatusItemProvider extends AbstractProvider
{
    public function __construct(
        private readonly PageTreeSelectionService $pageTreeSelectionService
    ) {
        parent::__construct();
    }

    /**
    * @var array<string, mixed>
    * @phpstan-ignore-next-line property.phpDocType
    */
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
        return ExtensionUtility::isRegisteredRecordTable($this->table) && $this->identifier !== '';
    }

    public function getPriority(): int
    {
        return 55;
    }

    /**
    * @param string|int $itemName
    * @return array<string, mixed>
    * @throws RouteNotFoundException
    */
    protected function getAdditionalAttributes(string|int $itemName): array
    {
        return [
            'data-callback-module' => '@xima/ximatypo3contentplanner/context-menu-actions',
            'data-status' => $itemName,
            'data-uri' => UrlHelper::getContentStatusPropertiesEditUrl($this->table, (int)$this->identifier, false),
            'data-new-comment-uri' => UrlHelper::getNewCommentUrl($this->table, (int)$this->identifier),
            'data-edit-uri' => UrlHelper::getContentStatusPropertiesEditUrl($this->table, (int)$this->identifier),
        ];
    }

    /**
    * @param array<string, mixed> $items
    * @return array<string, mixed>
    * @throws NotImplementedException|Exception
    * @phpstan-ignore-next-line property.phpDocType
    */
    public function addItems(array $items): array
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return $items;
        }
        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js');
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-list-modal.js');
        $pageRenderer->addInlineLanguageLabelFile('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf');

        $this->initDisabledItems();
        $itemsToAdd = $this->pageTreeSelectionService->generateSelection($this->table, (int)$this->identifier);

        if ($itemsToAdd === false) {
            return $items;
        }
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
