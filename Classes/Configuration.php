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

namespace Xima\XimaTypo3ContentPlanner;

use TYPO3\CMS\Backend\Controller\Page\TreeController as BackendTreeController;
use Xima\XimaTypo3ContentPlanner\Controller\TreeController;
use Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook;

/**
 * Configuration.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class Configuration
{
    final public const EXT_KEY = 'xima_typo3_content_planner';
    final public const EXT_NAME = 'XimaTypo3ContentPlanner';

    final public const FEATURE_AUTO_ASSIGN = 'autoAssignment';
    final public const FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT = 'currentAssigneeHighlight';
    final public const FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET = 'clearCommentsOnStatusReset';
    final public const FEATURE_RECORD_LIST_STATUS_INFO = 'recordListStatusInfo';
    final public const FEATURE_RECORD_EDIT_HEADER_INFO = 'recordEditHeaderInfo';
    final public const FEATURE_WEB_LIST_HEADER_INFO = 'webListHeaderInfo';
    final public const FEATURE_TREE_STATUS_INFORMATION = 'treeStatusInformation';
    final public const FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET = 'resetContentElementStatusOnPageReset';
    final public const FEATURE_COMMENT_TODOS = 'commentTodos';

    final public const CACHE_IDENTIFIER = 'ximatypo3contentplanner';

    final public const TABLE_FOLDER = 'tx_ximatypo3contentplanner_folder';
    final public const TABLE_COMMENT = 'tx_ximatypo3contentplanner_comment';
    final public const TABLE_STATUS = 'tx_ximatypo3contentplanner_status';

    final public const FIELD_STATUS = 'tx_ximatypo3contentplanner_status';
    final public const FIELD_ASSIGNEE = 'tx_ximatypo3contentplanner_assignee';
    final public const FIELD_COMMENTS = 'tx_ximatypo3contentplanner_comments';

    // Permission identifiers
    final public const PERMISSION_GROUP = 'tx_ximatypo3contentplanner';
    final public const PERMISSION_VIEW_ONLY = 'view-only';
    final public const PERMISSION_CONTENT_STATUS = 'content-status'; // Legacy key, now labeled "Full Access"
    final public const PERMISSION_FULL_ACCESS = 'full-access'; // Deprecated, use PERMISSION_CONTENT_STATUS

    // Status Permissions
    final public const PERMISSION_STATUS_CHANGE = 'status-change';
    final public const PERMISSION_STATUS_UNSET = 'status-unset';

    // Comment Permissions
    final public const PERMISSION_COMMENT_CREATE = 'comment-create';
    final public const PERMISSION_COMMENT_EDIT_OWN = 'comment-edit-own';
    final public const PERMISSION_COMMENT_EDIT_FOREIGN = 'comment-edit-foreign';
    final public const PERMISSION_COMMENT_RESOLVE = 'comment-resolve';
    final public const PERMISSION_COMMENT_DELETE_OWN = 'comment-delete-own';
    final public const PERMISSION_COMMENT_DELETE_FOREIGN = 'comment-delete-foreign';

    // Assignment Permissions
    final public const PERMISSION_ASSIGN_REASSIGN = 'assign-reassign';
    final public const PERMISSION_ASSIGN_OTHER_USER = 'assign-other-user';

    public static function overrideClasses(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][BackendTreeController::class] = [
            'className' => TreeController::class,
        ];
    }

    public static function registerHooks(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerHook::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = DataHandlerHook::class;
    }

    public static function registerCache(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['ximatypo3contentplanner_cache'] ??= [];
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['ximatypo3contentplanner_cache'] = DataHandlerHook::class.'->clearCachePostProc';
    }

    public static function addRegister(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][self::EXT_KEY]['registerAdditionalRecordTables'] = [];
    }

    public static function addRtePresets(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['comments'] = 'EXT:'.self::EXT_KEY.'/Configuration/RTE/Comments.yaml';
    }

    public static function registerUserSettings(): void
    {
        $GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3contentplanner_hide'] = [
            'label' => 'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
            'description' => 'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide.description',
            'type' => 'check',
            'table' => 'be_users',
        ];

        $GLOBALS['TYPO3_USER_SETTINGS']['showitem'] .= ',
            --div--;LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:content_planner,
                tx_ximatypo3contentplanner_hide,
        ';
    }

    public static function registerPermissions(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'][self::PERMISSION_GROUP] = [
            'header' => 'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.group',
            'items' => [
                // View only - enables feature visibility without any actions
                self::PERMISSION_VIEW_ONLY => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.view_only',
                    'actions-eye',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.view_only.description',
                ],
                // Full access - grants visibility and all permissions at once
                self::PERMISSION_CONTENT_STATUS => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.content_status',
                    'actions-check-circle-alt',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.content_status.description',
                ],
                // Status permissions
                self::PERMISSION_STATUS_CHANGE => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_change',
                    'actions-document-edit-access',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_change.description',
                ],
                self::PERMISSION_STATUS_UNSET => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_unset',
                    'actions-close',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_unset.description',
                ],
                // Comment permissions
                self::PERMISSION_COMMENT_CREATE => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_create',
                    'actions-plus',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_create.description',
                ],
                self::PERMISSION_COMMENT_EDIT_OWN => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_own',
                    'actions-open',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_own.description',
                ],
                self::PERMISSION_COMMENT_EDIT_FOREIGN => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_foreign',
                    'actions-open',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_foreign.description',
                ],
                self::PERMISSION_COMMENT_RESOLVE => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_resolve',
                    'actions-check-circle',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_resolve.description',
                ],
                self::PERMISSION_COMMENT_DELETE_OWN => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_own',
                    'actions-delete',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_own.description',
                ],
                self::PERMISSION_COMMENT_DELETE_FOREIGN => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_foreign',
                    'actions-delete',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_foreign.description',
                ],
                // Assignment permissions
                self::PERMISSION_ASSIGN_REASSIGN => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_reassign',
                    'actions-user',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_reassign.description',
                ],
                self::PERMISSION_ASSIGN_OTHER_USER => [
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_other_user',
                    'actions-user',
                    'LLL:EXT:'.self::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_other_user.description',
                ],
            ],
        ];
    }
}
