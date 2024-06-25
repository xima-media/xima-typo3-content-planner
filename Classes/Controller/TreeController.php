<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Controller;

use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Database\Query\Restriction\DocumentTypeExclusionRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * XClass the original \TYPO3\CMS\Backend\Controller\Page\TreeController to add the tx_ximacontentplanner_status field to additionalFieldsToQuery of the PageTreeRepository
 * Will be used within the AfterPageTreeItemsPreparedListener to display the tx_ximacontentplanner_status in the page tree
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
            ['subtitle', 'tx_ximacontentplanner_status'],
            $additionalQueryRestrictions
        );
        $pageTreeRepository->setAdditionalWhereClause($backendUser->getPagePermsClause(Permission::PAGE_SHOW));
        return $pageTreeRepository;
    }
}
