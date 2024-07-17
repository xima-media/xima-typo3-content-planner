<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class PermissionUtility
{
    public static function checkAccessForRecord(string $tablename, $record): bool
    {
        $backendUser = $GLOBALS['BE_USER'];
        /* @var $backendUser \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        if ($tablename === 'pages' && !BackendUtility::readPageAccess(
            $record['uid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW)
        )) {
            return false;
        }

        if (!$backendUser->check('tables_select', $tablename) || !BackendUtility::readPageAccess(
            $record['pid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW)
        )) {
            return false;
        }
        return true;
    }
}
