<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, RecordRepository, StatusRepository, SysFileMetadataRepository};
use Xima\XimaTypo3ContentPlanner\Service\FileList\FileListStatusService;
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ListSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;

use function array_key_exists;
use function is_array;

/**
 * FileListModifier.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class FileListModifier extends AbstractModifier implements ModifierInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        private readonly FileListStatusService $fileListStatusService,
        private readonly ListSelectionService $listSelectionService,
        private readonly SysFileMetadataRepository $sysFileMetadataRepository,
        private readonly FolderStatusRepository $folderStatusRepository,
        private readonly IconFactory $iconFactory,
        private readonly InfoGenerator $infoGenerator,
        private readonly \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer,
    ) {
        parent::__construct($statusRepository, $recordRepository);
    }

    public function isRelevant(ServerRequestInterface $request): bool
    {
        return SystemEnvironmentBuilder::REQUESTTYPE_BE === $request->getAttribute('applicationType')
            && ExtensionUtility::isFilelistSupportEnabled()
            && null !== $request->getAttribute('module')
            && RouteUtility::isFileListRoute($request->getAttribute('module')->getIdentifier());
    }

    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        if ('' === $content || !array_key_exists('id', $request->getQueryParams())) {
            return $response;
        }

        $folderIdentifier = $request->getQueryParams()['id'];
        // Detect tile view from content (more reliable than query param, which may not be present)
        $isTilesView = str_contains($content, 'class="resource-tiles"');

        $additionalCss = $this->fileListStatusService->generateStatusStyles($folderIdentifier, $isTilesView);

        $newContent = $content;

        // Add status header for current folder
        $newContent = $this->addFolderStatusHeader($newContent, $folderIdentifier);

        // Add status dropdowns to file and folder rows (only in list view, not tiles)
        if (!$isTilesView) {
            $newContent = $this->addStatusDropdownsToFiles($newContent, $folderIdentifier);
            $newContent = $this->addStatusDropdownsToFolders($newContent, $folderIdentifier);
        }

        if ('' !== $additionalCss) {
            $newContent = $this->injectFileListStyles($newContent, $additionalCss, $isTilesView);
        }

        // Load JavaScript module for handling status changes via AJAX
        $pageRenderer = $this->pageRenderer;
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/record-list-status.js');

        $newResponse = new Response();
        $newResponse->getBody()->write($newContent);

        return $newResponse;
    }

    private function addFolderStatusHeader(string $content, string $folderIdentifier): string
    {
        // Extract folder name from the identifier
        $folderName = $this->extractFolderName($folderIdentifier);

        $headerContent = $this->infoGenerator->generateFolderStatusHeader($folderIdentifier, $folderName);

        if (false === $headerContent || '' === $headerContent) {
            return $content;
        }

        // Check if t3-filelist-container is hidden (empty folder)
        $isContainerHidden = (bool) preg_match(
            '/<div\b[^>]*class="[^"]*t3-filelist-container[^"]*hidden[^"]*"[^>]*>/is',
            $content,
        );

        if ($isContainerHidden) {
            // Folder is empty: inject into t3-filelist-info-container instead
            $result = preg_replace(
                '/(<div\b[^>]*class="[^"]*t3-filelist-info-container[^"]*"[^>]*>)/is',
                '$1'.$headerContent,
                $content,
            );
        } else {
            // Normal case: inject into t3-filelist-container
            $result = preg_replace(
                '/(<div\b[^>]*class="[^"]*t3-filelist-container[^"]*"[^>]*>)/is',
                '$1'.$headerContent,
                $content,
            );
        }

        return $result ?? $content;
    }

    private function extractFolderName(string $combinedIdentifier): string
    {
        // Extract path from "1:/user_upload/subfolder/"
        $parts = explode(':', $combinedIdentifier, 2);
        $path = $parts[1] ?? $combinedIdentifier;

        // Remove trailing slash and get last segment
        $path = rtrim($path, '/');
        $segments = explode('/', $path);

        return end($segments) ?: $path;
    }

    private function addStatusDropdownsToFiles(string $content, string $folderIdentifier): string
    {
        $files = $this->sysFileMetadataRepository->findFilesByFolder($folderIdentifier);

        foreach ($files as $file) {
            $metadata = $this->sysFileMetadataRepository->findByIdentifier($file->getIdentifier());

            if (!is_array($metadata) || !array_key_exists('uid', $metadata)) {
                continue;
            }

            $metaUid = (int) $metadata['uid'];
            $status = null;

            if (isset($metadata[Configuration::FIELD_STATUS]) && 0 !== (int) $metadata[Configuration::FIELD_STATUS]) {
                $status = $this->statusRepository->findByUid((int) $metadata[Configuration::FIELD_STATUS]);
            }

            $dropdownItems = $this->listSelectionService->generateSelection('sys_file_metadata', $metaUid);
            $pattern = '/(<tr\b[^>]*data-filelist-meta-uid="'.$metaUid.'"[^>]*>.*?<div class="btn-group">)/is';
            $content = $this->injectDropdown($content, $status, $dropdownItems, $pattern);
        }

        return $content;
    }

    private function addStatusDropdownsToFolders(string $content, string $folderIdentifier): string
    {
        $subfolders = $this->folderStatusRepository->getAllSubfolders($folderIdentifier);

        foreach ($subfolders as $subfolder) {
            $status = null;
            $statusData = $subfolder['status'];

            if (is_array($statusData) && isset($statusData[Configuration::FIELD_STATUS]) && 0 !== (int) $statusData[Configuration::FIELD_STATUS]) {
                $status = $this->statusRepository->findByUid((int) $statusData[Configuration::FIELD_STATUS]);
            }

            $combinedIdentifier = $subfolder['combined_identifier'];
            $dropdownItems = $this->listSelectionService->generateFolderSelection($combinedIdentifier);
            $escapedIdentifier = preg_quote($combinedIdentifier, '/');
            $pattern = '/(<tr\b[^>]*data-filelist-identifier="'.$escapedIdentifier.'"[^>]*>.*?<div class="btn-group">)/is';
            $content = $this->injectDropdown($content, $status, $dropdownItems, $pattern);
        }

        return $content;
    }

    /**
     * @param array<string, string>|bool $dropdownItems
     */
    private function injectDropdown(string $content, ?Status $status, array|bool $dropdownItems, string $pattern): string
    {
        $title = $status instanceof Status ? htmlspecialchars($status->getTitle(), \ENT_QUOTES | \ENT_HTML5, 'UTF-8') : 'Status';
        $icon = $status instanceof Status ? $status->getColoredIcon() : 'flag-gray';

        $iconHtml = $this->iconFactory->getIcon($icon, IconUtility::getDefaultIconSize())->render();

        $dropdownItemsHtml = '';
        if (is_array($dropdownItems)) {
            foreach ($dropdownItems as $item) {
                $dropdownItemsHtml .= $item;
            }
        }

        $dropdown = '<div class="btn-group dropdown"><a href="#" class="btn btn-default btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="'.$title.'">'
            .$iconHtml.'</a><ul class="dropdown-menu">'.$dropdownItemsHtml.'</ul></div>';

        $result = preg_replace($pattern, '$1'.$dropdown, $content);

        return $result ?? $content;
    }

    private function injectFileListStyles(string $content, string $css, bool $isTilesView): string
    {
        if ($isTilesView) {
            $result = preg_replace(
                '/(<div\b[^>]*class="[^"]*resource-tiles[^"]*"[^>]*>)/is',
                '<style>'.$css.'</style>$1',
                $content,
            );
        } else {
            $result = preg_replace(
                '/(<table\b[^>]*id="typo3-filelist"[^>]*>)/is',
                '<style>'.$css.'</style>$1',
                $content,
            );
        }

        return $result ?? $content;
    }
}
