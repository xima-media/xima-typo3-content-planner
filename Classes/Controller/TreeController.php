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

namespace Xima\XimaTypo3ContentPlanner\Controller;

use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Database\Query\Restriction\DocumentTypeExclusionRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

/**
 * TreeController.
 *
 * Registered as an XCLASS of core's page tree TreeController in
 * Configuration::overrideClasses() (see that method for the full rationale
 * and its conflict potential with other extensions). Keep this override
 * limited to initializePageTreeRepository() below: it is the only method
 * that needs to change, and every line beyond the two-value $fields array
 * merely duplicates core's own implementation so it stays a drop-in
 * replacement across TYPO3 versions.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class TreeController extends \TYPO3\CMS\Backend\Controller\Page\TreeController
{
    /**
     * Identical to core's TreeController::initializePageTreeRepository(),
     * except for passing [Configuration::FIELD_STATUS, Configuration::
     * FIELD_COMMENTS] instead of an empty array as $additionalFieldsToQuery,
     * so those columns end up on every tree node's `_page` data without an
     * extra query per node.
     */
    protected function initializePageTreeRepository(): PageTreeRepository
    {
        $backendUser = $this->getBackendUser();
        $userTsConfig = $backendUser->getTSConfig();
        $excludedDocumentTypes = GeneralUtility::intExplode(',', (string) ($userTsConfig['options.']['pageTree.']['excludeDoktypes'] ?? ''), true);

        $additionalQueryRestrictions = [];
        if ([] !== $excludedDocumentTypes) {
            $additionalQueryRestrictions[] = GeneralUtility::makeInstance(DocumentTypeExclusionRestriction::class, $excludedDocumentTypes);
        }

        $pageTreeRepository = GeneralUtility::makeInstance(
            PageTreeRepository::class,
            $backendUser->workspace,
            [Configuration::FIELD_STATUS, Configuration::FIELD_COMMENTS],
            $additionalQueryRestrictions,
        );
        $pageTreeRepository->setAdditionalWhereClause($backendUser->getPagePermsClause(Permission::PAGE_SHOW));

        return $pageTreeRepository;
    }
}
