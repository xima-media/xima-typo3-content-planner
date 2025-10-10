<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

use function is_array;

/**
 * PermissionUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class PermissionUtility
{
    /**
     * @param array<string, mixed>|bool $record
     */
    public static function checkAccessForRecord(string $table, $record): bool
    {
        if (!is_array($record)) {
            return false;
        }

        $backendUser = $GLOBALS['BE_USER'];
        if (null === $backendUser->user) {
            Bootstrap::initializeBackendAuthentication();
            $backendUser->initializeUserSessionManager();
            $backendUser = $GLOBALS['BE_USER'];
        }

        if ('_cli_' === $backendUser->user['username']) {
            return true;
        }

        /* @var $backendUser \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        if ('pages' === $table && !BackendUtility::readPageAccess(
            $record['uid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW),
        )) {
            return false;
        }

        if (!$backendUser->check('tables_select', $table) || !BackendUtility::readPageAccess(
            $record['pid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW),
        )) {
            return false;
        }

        return true;
    }
}
