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
use TYPO3\CMS\Core\Core\{RequestId, SystemEnvironmentBuilder};
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, RecordRepository, StatusRepository, SysFileMetadataRepository};
use Xima\XimaTypo3ContentPlanner\Service\FileList\FileListStatusService;
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ListSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, IconUtility};

use function array_key_exists;
use function is_array;
use function sprintf;

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
        private readonly RequestId $requestId,
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
        // Detect tile view from content (more reliable than query param, which may not be present).
        // The container carries more classes in TYPO3 v14 ("resource-tiles my-3"), so match the class, not the attribute.
        $isTilesView = 1 === preg_match('/class="[^"]*\bresource-tiles\b[^"]*"/', $content);

        $additionalCss = $this->fileListStatusService->generateStatusStyles($folderIdentifier, $isTilesView);

        $newContent = $content;

        // The doc header group carries the folder status in "chip" and "docked" mode, the banner
        // only belongs to the legacy "banner" mode.
        if (ExtensionUtility::isBannerDisplayModeEnabled()) {
            $newContent = $this->addFolderStatusHeader($newContent, $folderIdentifier);
        }

        // Add status dropdowns to file and folder rows (only in list view, not tiles)
        if (!$isTilesView) {
            $newContent = $this->addStatusDropdownsToFiles($newContent, $folderIdentifier);
            $newContent = $this->addStatusDropdownsToFolders($newContent, $folderIdentifier);
        }

        $newContent = $this->injectFileListStyles($newContent, $this->buildInjection($additionalCss, $isTilesView), $isTilesView);

        // Load JavaScript module for handling status changes via AJAX
        $pageRenderer = $this->pageRenderer;
        $pageRenderer->loadJavaScriptModule(Configuration::JAVASCRIPT_MODULE_PREFIX.'record-list-status.js');

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
        $languageService = $this->getLanguageService();
        $label = htmlspecialchars($status instanceof Status ? $status->getTitle() : $languageService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:status.set'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $icon = $status instanceof Status ? $status->getColoredIcon() : 'flag-gray';

        $iconHtml = $this->iconFactory->getIcon($icon, IconUtility::getDefaultIconSize())->render();

        $dropdownItemsHtml = '';
        if (is_array($dropdownItems)) {
            foreach ($dropdownItems as $item) {
                $dropdownItemsHtml .= $item;
            }
        }

        // The visible text is the accessible name. Without a status the label is muted.
        $title = $status instanceof Status
            ? ' title="'.htmlspecialchars(sprintf($languageService->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:status.change'), $status->getTitle()), \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'"'
            : '';
        $class = $status instanceof Status ? '' : ' content-planner-status-action--none';

        $dropdown = '<div class="btn-group dropdown"><button type="button" class="btn btn-default btn-sm dropdown-toggle'.$class.'" data-bs-toggle="dropdown" aria-expanded="false"'.$title.'>'
            .$iconHtml.' '.$label.'</button><ul class="dropdown-menu">'.$dropdownItemsHtml.'</ul></div>';

        $result = preg_replace($pattern, '$1'.$dropdown, $content);

        return $result ?? $content;
    }

    /**
     * Runs after the page rendered, so Status.css (muted "Set status") travels as a link with the markup.
     */
    private function buildInjection(string $css, bool $isTilesView): string
    {
        $stylesheet = $isTilesView ? '' : AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Status.css',
            ['nonce' => $this->requestId->nonce],
        );

        return $stylesheet.('' !== $css ? '<style>'.$css.'</style>' : '');
    }

    private function injectFileListStyles(string $content, string $injection, bool $isTilesView): string
    {
        if ('' === $injection) {
            return $content;
        }

        if ($isTilesView) {
            $result = preg_replace(
                '/(<div\b[^>]*class="[^"]*resource-tiles[^"]*"[^>]*>)/is',
                $injection.'$1',
                $content,
                1,
            );
        } else {
            $result = preg_replace(
                '/(<table\b[^>]*id="typo3-filelist"[^>]*>)/is',
                $injection.'$1',
                $content,
                1,
            );
        }

        return $result ?? $content;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
