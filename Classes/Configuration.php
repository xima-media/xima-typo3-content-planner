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
}
