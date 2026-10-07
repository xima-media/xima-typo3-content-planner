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
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
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
        private readonly PageRenderer $pageRenderer,
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
            $newContent = $this->addStatusDropdownsToFiles($newContent);
            $newContent = $this->addStatusDropdownsToFolders($newContent, $folderIdentifier);
        }

        if ('' !== $additionalCss) {
            $newContent = $this->injectFileListStyles($newContent, $additionalCss, $isTilesView);
        }

        // Load JavaScript module for handling status changes via AJAX
        $pageRenderer = $this->pageRenderer;
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/record-list-status.js');

        return $this->replaceBody($response, $newContent);
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

    /**
     * Works only on the rows the file list actually rendered (it paginates), with a single
     * batched metadata query and one pass over the HTML, so the cost no longer grows with
     * the number of files in the folder.
     */
    private function addStatusDropdownsToFiles(string $content): string
    {
        preg_match_all('/<tr\b[^>]*\bdata-filelist-meta-uid="(\d+)"/i', $content, $matches);
        $metadataByUid = $this->sysFileMetadataRepository->findByUids(array_map(intval(...), $matches[1]));

        if ([] === $metadataByUid) {
            return $content;
        }

        // Stops at </tr>, so a row without a button group cannot pull the next row's into its match.
        // Possessive and atomic, so long rows cannot exhaust the PCRE JIT stack and drop every dropdown at once.
        $result = preg_replace_callback(
            '/<tr\b[^>]*\bdata-filelist-meta-uid="(\d+)"[^>]*>(?>[^<]++|<(?!\/tr>|div class="btn-group">))*+<div class="btn-group">/i',
            fn (array $match): string => $match[0].$this->buildFileDropdown($metadataByUid[(int) $match[1]] ?? null),
            $content,
        );

        return $result ?? $content;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function buildFileDropdown(?array $metadata): string
    {
        if (null === $metadata) {
            return '';
        }

        $status = null;
        if (isset($metadata[Configuration::FIELD_STATUS]) && 0 !== (int) $metadata[Configuration::FIELD_STATUS]) {
            $status = $this->statusRepository->findByUid((int) $metadata[Configuration::FIELD_STATUS]);
        }

        $dropdownItems = $this->listSelectionService->generateSelection('sys_file_metadata', (int) $metadata['uid'], $metadata);

        return $this->buildDropdown($status, $dropdownItems);
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
            $dropdown = $this->buildDropdown($status, $this->listSelectionService->generateFolderSelection($combinedIdentifier));
            $pattern = '/<tr\b[^>]*data-filelist-identifier="'.preg_quote($combinedIdentifier, '/').'"[^>]*>.*?<div class="btn-group">/is';
            // A callback instead of a replacement string, so "$1" or "\1" in a status title stays literal.
            $content = preg_replace_callback($pattern, static fn (array $match): string => $match[0].$dropdown, $content) ?? $content;
        }

        return $content;
    }

    /**
     * @param array<string, string>|bool $dropdownItems
     */
    private function buildDropdown(?Status $status, array|bool $dropdownItems): string
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

        return '<div class="btn-group dropdown"><button type="button" class="btn btn-default btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="'.$title.'">'
            .$iconHtml.'</button><ul class="dropdown-menu">'.$dropdownItemsHtml.'</ul></div>';
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
