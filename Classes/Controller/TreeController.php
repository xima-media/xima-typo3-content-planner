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

namespace Xima\XimaTypo3ContentPlanner\Controller;

use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Database\Query\Restriction\DocumentTypeExclusionRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TreeController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class TreeController extends \TYPO3\CMS\Backend\Controller\Page\TreeController
{
    protected function initializePageTreeRepository(): PageTreeRepository
    {
        $backendUser = $this->getBackendUser();
        $userTsConfig = $backendUser->getTSConfig();
        $excludedDocumentTypes = GeneralUtility::intExplode(',', (string)($userTsConfig['options.']['pageTree.']['excludeDoktypes'] ?? ''), true);

        $additionalQueryRestrictions = [];
        if ($excludedDocumentTypes !== []) {
            $additionalQueryRestrictions[] = GeneralUtility::makeInstance(DocumentTypeExclusionRestriction::class, $excludedDocumentTypes);
        }

        $pageTreeRepository = GeneralUtility::makeInstance(
            PageTreeRepository::class,
            $backendUser->workspace,
            ['tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_comments'],
            $additionalQueryRestrictions
        );
        $pageTreeRepository->setAdditionalWhereClause($backendUser->getPagePermsClause(Permission::PAGE_SHOW));
        return $pageTreeRepository;
    }
}
