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

namespace Xima\XimaTypo3ContentPlanner\Utility\Security;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

use function is_array;

/**
 * PermissionUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
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

        if (!$backendUser->check('tables_select', $table)) {
            return false;
        }

        // Check page access only if record has a pid (not applicable for sys_file_metadata, folders, etc.)
        if (isset($record['pid']) && !BackendUtility::readPageAccess(
            (int) $record['pid'],
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW),
        )) {
            return false;
        }

        return true;
    }

    /**
     * Check if content status visibility is allowed for the current user.
     * Checks permission and user settings.
     */
    public static function checkContentStatusVisibility(): bool
    {
        // check permission
        if (!$GLOBALS['BE_USER']->isAdmin() && !$GLOBALS['BE_USER']->check('custom_options', 'tx_ximatypo3contentplanner:content-status')) {
            return false;
        }

        // check user setting
        if (1 === $GLOBALS['BE_USER']->user['tx_ximatypo3contentplanner_hide']) {
            return false;
        }

        return true;
    }
}
