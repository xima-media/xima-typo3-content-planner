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

namespace Xima\XimaTypo3ContentPlanner\Service\FileList;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, StatusRepository, SysFileMetadataRepository};

/**
 * FileListStatusService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class FileListStatusService
{
    public function __construct(
        private readonly SysFileMetadataRepository $sysFileMetadataRepository,
        private readonly FolderStatusRepository $folderStatusRepository,
        private readonly StatusRepository $statusRepository,
    ) {}

    /**
     * Generate CSS styles for status visualization in the file list.
     */
    public function generateStatusStyles(string $folderIdentifier, bool $isTilesView): string
    {
        $css = [];

        $this->addFileStatusStyles($css, $folderIdentifier, $isTilesView);
        $this->addFolderStatusStyles($css, $folderIdentifier, $isTilesView);

        return implode(' ', $css);
    }

    /**
     * @param string[] $css
     *
     * @throws Exception
     */
    private function addFileStatusStyles(array &$css, string $folderIdentifier, bool $isTilesView): void
    {
        $files = $this->sysFileMetadataRepository->findFilesByFolder($folderIdentifier);
        foreach ($files as $file) {
            $metadata = $this->sysFileMetadataRepository->findByIdentifier($file->getIdentifier());
            if (!$this->hasValidStatus($metadata)) {
                continue;
            }

            $status = $this->statusRepository->findByUid((int) $metadata[Configuration::FIELD_STATUS]);
            if (!$status instanceof Status) {
                continue;
            }

            $css[] = $this->buildFileCssRule((int) $metadata['uid'], $status, $isTilesView);
        }
    }

    /**
     * @param string[] $css
     *
     * @throws Exception
     */
    private function addFolderStatusStyles(array &$css, string $folderIdentifier, bool $isTilesView): void
    {
        $subfolders = $this->folderStatusRepository->findSubfoldersWithStatus($folderIdentifier);
        foreach ($subfolders as $subfolder) {
            $status = $this->statusRepository->findByUid((int) $subfolder[Configuration::FIELD_STATUS]);
            if (!$status instanceof Status) {
                continue;
            }

            $css[] = $this->buildFolderCssRule($subfolder['combined_identifier'], $status, $isTilesView);
        }
    }

    /**
     * @param array<string, mixed>|false $metadata
     */
    private function hasValidStatus(array|false $metadata): bool
    {
        return $metadata
            && null !== $metadata[Configuration::FIELD_STATUS]
            && 0 !== (int) $metadata[Configuration::FIELD_STATUS];
    }

    private function buildFileCssRule(int $metaUid, Status $status, bool $isTilesView): string
    {
        $color = Configuration\Colors::get($status->getColor(), true);

        if ($isTilesView) {
            return '.resource-tile[data-filelist-meta-uid="'.$metaUid.'"] { background-color: '.$color.'; }';
        }

        return 'tr[data-filelist-meta-uid="'.$metaUid.'"] > td { background-color: '.$color.'; }';
    }

    private function buildFolderCssRule(string $combinedIdentifier, Status $status, bool $isTilesView): string
    {
        $color = Configuration\Colors::get($status->getColor(), true);
        $escapedIdentifier = htmlspecialchars($combinedIdentifier, \ENT_QUOTES);

        if ($isTilesView) {
            return '.resource-tile[data-filelist-identifier="'.$escapedIdentifier.'"] { background-color: '.$color.'; }';
        }

        return 'tr[data-filelist-identifier="'.$escapedIdentifier.'"] > td { background-color: '.$color.'; }';
    }
}
