<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class PermissionUtility
{
    public static function checkAccessForRecord(string $table, $record): bool
    {
        $backendUser = $GLOBALS['BE_USER'];
        if ($backendUser->user === null) {
            Bootstrap::initializeBackendAuthentication();
            $backendUser->initializeUserSessionManager();
            $backendUser = $GLOBALS['BE_USER'];
        }

        if ($backendUser->user['username'] === '_cli_') {
            return true;
        }

        /* @var $backendUser \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        if ($table === 'pages' && !BackendUtility::readPageAccess(
            $record['uid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW)
        )) {
            return false;
        }

        if (!$backendUser->check('tables_select', $table) || !BackendUtility::readPageAccess(
            $record['pid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW)
        )) {
            return false;
        }
        return true;
    }
}
