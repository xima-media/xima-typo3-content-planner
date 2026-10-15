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
        $css = [
            ...$this->addFileStatusStyles($folderIdentifier, $isTilesView),
            ...$this->addFolderStatusStyles($folderIdentifier, $isTilesView),
        ];

        return implode(' ', $css);
    }

    /**
     * @return string[]
     *
     * @throws Exception
     */
    private function addFileStatusStyles(string $folderIdentifier, bool $isTilesView): array
    {
        $css = [];

        // findByFolderWithStatus() already batches the metadata lookup and filters to files with a status.
        foreach ($this->sysFileMetadataRepository->findByFolderWithStatus($folderIdentifier) as $metadata) {
            $status = $this->statusRepository->findByUid((int) $metadata[Configuration::FIELD_STATUS]);
            if (!$status instanceof Status) {
                continue;
            }

            $css[] = $this->buildFileCssRule((int) $metadata['uid'], $status, $isTilesView);
        }

        return $css;
    }

    /**
     * @return string[]
     *
     * @throws Exception
     */
    private function addFolderStatusStyles(string $folderIdentifier, bool $isTilesView): array
    {
        $css = [];

        $subfolders = $this->folderStatusRepository->findSubfoldersWithStatus($folderIdentifier);
        foreach ($subfolders as $subfolder) {
            $status = $this->statusRepository->findByUid((int) $subfolder[Configuration::FIELD_STATUS]);
            if (!$status instanceof Status) {
                continue;
            }

            $css[] = $this->buildFolderCssRule($subfolder['combined_identifier'], $status, $isTilesView);
        }

        return $css;
    }

    private function buildFileCssRule(int $metaUid, Status $status, bool $isTilesView): string
    {
        return $this->buildEdgeRule('data-filelist-meta-uid="'.$metaUid.'"', $status, $isTilesView);
    }

    private function buildFolderCssRule(string $combinedIdentifier, Status $status, bool $isTilesView): string
    {
        return $this->buildEdgeRule('data-filelist-identifier="'.htmlspecialchars($combinedIdentifier, \ENT_QUOTES).'"', $status, $isTilesView);
    }

    /**
     * An edge instead of a tint (docs/design/rules.md, "Status presentation"): 4px inset on the
     * first cell of a row, 3px on the top of a tile.
     */
    private function buildEdgeRule(string $attribute, Status $status, bool $isTilesView): string
    {
        $edge = Configuration\Colors::getEdge($status->getColor());

        if ($isTilesView) {
            return '.resource-tile['.$attribute.'] { border-top: 3px solid '.$edge.'; }';
        }

        return 'tr['.$attribute.'] > td:first-child { box-shadow: inset 4px 0 0 '.$edge.'; }';
    }
}
