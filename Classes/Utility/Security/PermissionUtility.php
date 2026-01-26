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

namespace Xima\XimaTypo3ContentPlanner\Utility\Security;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function in_array;
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
        if ('pages' === $table && isset($record['uid']) && !BackendUtility::readPageAccess(
            (int) $record['uid'],
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
        if (!$GLOBALS['BE_USER']->isAdmin() && !self::checkPermission(Configuration::PERMISSION_CONTENT_STATUS)) {
            return false;
        }

        // check user setting
        if (1 === ($GLOBALS['BE_USER']->user['tx_ximatypo3contentplanner_hide'] ?? 0)) {
            return false;
        }

        return true;
    }

    // ==================== Status Permissions ====================

    /**
     * Check if user can change the status of records.
     */
    public static function canChangeStatus(?int $statusUid = null): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        if (!self::checkPermission(Configuration::PERMISSION_STATUS_CHANGE)) {
            return false;
        }

        // If a specific status is requested, check if it's allowed
        if (null !== $statusUid && !self::isStatusAllowedForUser($statusUid)) {
            return false;
        }

        return true;
    }

    /**
     * Check if user can unset/remove the status from records.
     */
    public static function canUnsetStatus(): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        return self::checkPermission(Configuration::PERMISSION_STATUS_UNSET);
    }

    /**
     * Check if a specific status is allowed for the current user.
     */
    public static function isStatusAllowedForUser(int $statusUid): bool
    {
        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        $allowedStatuses = self::getAllowedStatusUidsForUser();

        // Empty means all statuses are allowed
        if ([] === $allowedStatuses) {
            return true;
        }

        return in_array($statusUid, $allowedStatuses, true);
    }

    /**
     * Check if a specific table is allowed for the current user.
     */
    public static function isTableAllowedForUser(string $table): bool
    {
        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        $allowedTables = self::getAllowedTablesForUser();

        // Empty means all tables are allowed
        if ([] === $allowedTables) {
            return true;
        }

        return in_array($table, $allowedTables, true);
    }

    // ==================== Comment Permissions ====================

    /**
     * Check if user can edit a specific comment.
     *
     * @param array<string, mixed> $comment
     */
    public static function canEditComment(array $comment): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        // Check if it's the user's own comment
        if (self::isOwnComment($comment)) {
            return self::checkPermission(Configuration::PERMISSION_COMMENT_EDIT_OWN);
        }

        // Check if user can edit foreign comments
        return self::checkPermission(Configuration::PERMISSION_COMMENT_EDIT_FOREIGN);
    }

    /**
     * Check if user can delete a specific comment.
     *
     * @param array<string, mixed> $comment
     */
    public static function canDeleteComment(array $comment): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        // Check if it's the user's own comment
        if (self::isOwnComment($comment)) {
            return self::checkPermission(Configuration::PERMISSION_COMMENT_DELETE_OWN);
        }

        // Check if user can delete foreign comments
        return self::checkPermission(Configuration::PERMISSION_COMMENT_DELETE_FOREIGN);
    }

    /**
     * Check if user can resolve/unresolve comments.
     */
    public static function canResolveComment(): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        return self::checkPermission(Configuration::PERMISSION_COMMENT_RESOLVE);
    }

    /**
     * Check if the given comment belongs to the current user.
     *
     * @param array<string, mixed> $comment
     */
    public static function isOwnComment(array $comment): bool
    {
        $currentUserId = (int) ($GLOBALS['BE_USER']->user['uid'] ?? 0);
        $authorId = (int) ($comment['author'] ?? 0);

        return $currentUserId > 0 && $currentUserId === $authorId;
    }

    // ==================== Assignment Permissions ====================

    /**
     * Check if user can reassign (change existing assignee).
     */
    public static function canReassign(): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        return self::checkPermission(Configuration::PERMISSION_ASSIGN_REASSIGN);
    }

    /**
     * Check if user can assign other users (not just themselves).
     */
    public static function canAssignOtherUser(): bool
    {
        if (!self::checkContentStatusVisibility()) {
            return false;
        }

        if ($GLOBALS['BE_USER']->isAdmin() || self::hasFullAccess()) {
            return true;
        }

        return self::checkPermission(Configuration::PERMISSION_ASSIGN_OTHER_USER);
    }

    /**
     * Check if user can assign themselves.
     * This is always allowed if the user has content status visibility.
     */
    public static function canAssignSelf(): bool
    {
        return self::checkContentStatusVisibility();
    }

    // ==================== Helper Methods ====================

    /**
     * Check if user has full access permission (grants all permissions).
     * This includes:
     * - Explicit full-access permission
     * - Legacy mode: content-status without any granular permissions (backward compatibility).
     */
    public static function hasFullAccess(): bool
    {
        // Explicit full-access permission
        if (self::checkPermission(Configuration::PERMISSION_FULL_ACCESS)) {
            return true;
        }

        // Legacy mode: user has content-status but no granular permissions
        // This ensures backward compatibility for existing installations
        if (self::checkPermission(Configuration::PERMISSION_CONTENT_STATUS) && !self::hasAnyGranularPermission()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has any granular permission configured.
     * Used to determine if user is in "granular mode" or "legacy mode".
     */
    private static function hasAnyGranularPermission(): bool
    {
        $granularPermissions = [
            Configuration::PERMISSION_FULL_ACCESS,
            Configuration::PERMISSION_STATUS_CHANGE,
            Configuration::PERMISSION_STATUS_UNSET,
            Configuration::PERMISSION_COMMENT_EDIT_OWN,
            Configuration::PERMISSION_COMMENT_EDIT_FOREIGN,
            Configuration::PERMISSION_COMMENT_RESOLVE,
            Configuration::PERMISSION_COMMENT_DELETE_OWN,
            Configuration::PERMISSION_COMMENT_DELETE_FOREIGN,
            Configuration::PERMISSION_ASSIGN_REASSIGN,
            Configuration::PERMISSION_ASSIGN_OTHER_USER,
        ];

        foreach ($granularPermissions as $permission) {
            if (self::checkPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check a specific custom permission for the current user.
     */
    private static function checkPermission(string $permission): bool
    {
        return $GLOBALS['BE_USER']->check(
            'custom_options',
            Configuration::PERMISSION_GROUP.':'.$permission,
        );
    }

    /**
     * Get the list of allowed status UIDs for the current user.
     *
     * @return array<int, int>
     */
    private static function getAllowedStatusUidsForUser(): array
    {
        $userGroupIds = GeneralUtility::intExplode(',', (string) ($GLOBALS['BE_USER']->user['usergroup'] ?? ''), true);

        if ([] === $userGroupIds) {
            return [];
        }

        $allowedStatuses = [];

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_groups');

        $result = $connection->select(
            ['tx_ximatypo3contentplanner_allowed_statuses'],
            'be_groups',
            ['uid' => $userGroupIds],
        );

        while ($row = $result->fetchAssociative()) {
            $statuses = GeneralUtility::intExplode(',', (string) ($row['tx_ximatypo3contentplanner_allowed_statuses'] ?? ''), true);
            $allowedStatuses = array_merge($allowedStatuses, $statuses);
        }

        return array_unique($allowedStatuses);
    }

    /**
     * Get the list of allowed tables for the current user.
     *
     * @return array<int, string>
     */
    private static function getAllowedTablesForUser(): array
    {
        $userGroupIds = GeneralUtility::intExplode(',', (string) ($GLOBALS['BE_USER']->user['usergroup'] ?? ''), true);

        if ([] === $userGroupIds) {
            return [];
        }

        $allowedTables = [];

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('be_groups');

        $result = $connection->select(
            ['tx_ximatypo3contentplanner_allowed_tables'],
            'be_groups',
            ['uid' => $userGroupIds],
        );

        while ($row = $result->fetchAssociative()) {
            $tables = GeneralUtility::trimExplode(',', (string) ($row['tx_ximatypo3contentplanner_allowed_tables'] ?? ''), true);
            $allowedTables = array_merge($allowedTables, $tables);
        }

        return array_unique($allowedTables);
    }
}
