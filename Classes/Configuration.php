<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner;

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
