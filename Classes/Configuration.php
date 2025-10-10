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

namespace Xima\XimaTypo3ContentPlanner;

/**
 * Configuration.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
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
}
