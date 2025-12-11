<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Backend\ContextMenu\ItemProviders;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, SysFileMetadataRepository};
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ContextMenuSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_slice;
use function in_array;

/**
 * StatusItemProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusItemProvider extends AbstractProvider
{
    /**
     * @var array<string, mixed>
     *
     * @phpstan-ignore-next-line property.phpDocType
     */
    protected $itemsConfiguration = [
        'wrap' => [
            'type' => 'submenu',
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:status',
            'iconIdentifier' => 'flag-gray',
            'childItems' => [],
        ],
    ];

    private string $effectiveTable = '';

    private int $effectiveIdentifier = 0;

    private bool $isFolder = false;

    private string $folderIdentifier = '';

    public function __construct(
        private readonly ContextMenuSelectionService $contextMenuSelectionService,
        private readonly SysFileMetadataRepository $sysFileMetadataRepository,
        private readonly FolderStatusRepository $folderStatusRepository,
        private readonly UriBuilder $uriBuilder,
    ) {
        parent::__construct();
    }

    public function canHandle(): bool
    {
        if ('sys_file' === $this->table && ExtensionUtility::isFilelistSupportEnabled()) {
            return '' !== $this->identifier;
        }

        return ExtensionUtility::isRegisteredRecordTable($this->table) && '' !== $this->identifier;
    }

    public function getPriority(): int
    {
        return 73;
    }

    /**
     * @param array<string, mixed> $items
     *
     * @return array<string, mixed>
     *
     * @throws NotImplementedException|Exception
     *
     * @phpstan-ignore-next-line property.phpDocType
     */
    public function addItems(array $items): array
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return $items;
        }

        if (!$this->resolveEffectiveRecord()) {
            return $items;
        }

        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js');
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-list-modal.js');
        $pageRenderer->addInlineLanguageLabelFile('EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf');

        $this->initDisabledItems();

        // Use folder selection for folders, regular selection for files/records
        if ($this->isFolder) {
            $itemsToAdd = $this->contextMenuSelectionService->generateFolderSelection($this->folderIdentifier);
        } else {
            $itemsToAdd = $this->contextMenuSelectionService->generateSelection($this->effectiveTable, $this->effectiveIdentifier);
        }

        if (false === $itemsToAdd) {
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
            $items += $localItems;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RouteNotFoundException
     */
    protected function getAdditionalAttributes(string|int $itemName): array
    {
        $attributes = [
            'data-callback-module' => '@xima/ximatypo3contentplanner/context-menu-actions',
            'data-status' => $itemName,
            'data-effective-table' => $this->effectiveTable,
            'data-effective-uid' => $this->effectiveIdentifier,
        ];

        if ($this->isFolder) {
            // For folders, use the folder status update endpoint
            $attributes['data-folder-identifier'] = $this->folderIdentifier;
            $attributes['data-folder-status-url'] = (string) $this->uriBuilder->buildUriFromRoute(
                'ximatypo3contentplanner_folder_status_update',
                ['identifier' => $this->folderIdentifier],
            );

            // Only add edit/comment URLs if folder record exists (has been assigned a status before)
            if ($this->effectiveIdentifier > 0) {
                $attributes['data-uri'] = UrlUtility::getContentStatusPropertiesEditUrl($this->effectiveTable, $this->effectiveIdentifier, false);
                $attributes['data-new-comment-uri'] = UrlUtility::getNewCommentUrl($this->effectiveTable, $this->effectiveIdentifier);
                $attributes['data-edit-uri'] = UrlUtility::getContentStatusPropertiesEditUrl($this->effectiveTable, $this->effectiveIdentifier);
            }
        } else {
            $attributes['data-uri'] = UrlUtility::getContentStatusPropertiesEditUrl($this->effectiveTable, $this->effectiveIdentifier, false);
            $attributes['data-new-comment-uri'] = UrlUtility::getNewCommentUrl($this->effectiveTable, $this->effectiveIdentifier);
            $attributes['data-edit-uri'] = UrlUtility::getContentStatusPropertiesEditUrl($this->effectiveTable, $this->effectiveIdentifier);
        }

        return $attributes;
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

    /**
     * Resolve the effective table and identifier for file/folder metadata mapping.
     *
     * @throws Exception
     */
    private function resolveEffectiveRecord(): bool
    {
        if ('sys_file' === $this->table) {
            // Check if this is a folder (identifiers ending with /)
            if (str_ends_with($this->identifier, '/')) {
                $this->isFolder = true;
                $this->folderIdentifier = $this->identifier;
                $this->effectiveTable = Configuration::TABLE_FOLDER;

                // Try to find existing folder status record
                $folderRecord = $this->folderStatusRepository->findByCombinedIdentifier($this->identifier);
                $this->effectiveIdentifier = $folderRecord ? (int) $folderRecord['uid'] : 0;

                return true;
            }

            // It's a file
            $metadata = $this->resolveFileMetadata();
            if (!$metadata) {
                return false;
            }
            $this->effectiveTable = 'sys_file_metadata';
            $this->effectiveIdentifier = (int) $metadata['uid'];
        } else {
            $this->effectiveTable = $this->table;
            $this->effectiveIdentifier = (int) $this->identifier;
        }

        return true;
    }

    /**
     * Resolve file metadata from various identifier formats.
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    private function resolveFileMetadata(): array|false
    {
        // Check if identifier is numeric (sys_file UID)
        if (is_numeric($this->identifier)) {
            return $this->sysFileMetadataRepository->findByFileUid((int) $this->identifier);
        }

        // Check if identifier is a combined identifier (e.g., "1:/user_upload/file.pdf")
        if (str_contains($this->identifier, ':')) {
            $parts = explode(':', $this->identifier, 2);
            if (isset($parts[1])) {
                return $this->sysFileMetadataRepository->findByIdentifier($parts[1]);
            }
        }

        // Try as direct path identifier
        return $this->sysFileMetadataRepository->findByIdentifier($this->identifier);
    }
}
