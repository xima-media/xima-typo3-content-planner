<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

class PermissionUtility
{
    public static function checkAccessForRecord(string $tablename, $record): bool
    {
        $backendUser = $GLOBALS['BE_USER'];
        /* @var $backendUser \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */

        // ToDo: seems a bit ugly, but it works (for now)
        if ($tablename === 'pages' && !$backendUser->doesUserHaveAccess($record, 1)) {
            return false;
        }
        if (!$backendUser->check('tables_modify', $tablename)) {
            return false;
        }
        return true;
    }
}
