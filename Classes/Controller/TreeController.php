<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
        $excludedDocumentTypes = GeneralUtility::intExplode(',', (string) ($userTsConfig['options.']['pageTree.']['excludeDoktypes'] ?? ''), true);

        $additionalQueryRestrictions = [];
        if ([] !== $excludedDocumentTypes) {
            $additionalQueryRestrictions[] = GeneralUtility::makeInstance(DocumentTypeExclusionRestriction::class, $excludedDocumentTypes);
        }

        $pageTreeRepository = GeneralUtility::makeInstance(
            PageTreeRepository::class,
            $backendUser->workspace,
            ['tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_comments'],
            $additionalQueryRestrictions,
        );
        $pageTreeRepository->setAdditionalWhereClause($backendUser->getPagePermsClause(Permission::PAGE_SHOW));

        return $pageTreeRepository;
    }
}
