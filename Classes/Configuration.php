<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner;

class Configuration
{
    final public const EXT_KEY = 'xima_typo3_content_planner';
    final public const EXT_NAME = 'XimaTypo3ContentPlanner';

    final public const STATUS_ICON_FLAG = 'flag';
    final public const STATUS_ICON_HEART = 'heart';
    final public const STATUS_ICON_TAG = 'tag';
    final public const STATUS_ICON_STAR = 'star';
    final public const STATUS_ICON_INFO = 'info';

    final public const STATUS_ICONS = [
        self::STATUS_ICON_FLAG,
        self::STATUS_ICON_HEART,
        self::STATUS_ICON_TAG,
        self::STATUS_ICON_STAR,
        self::STATUS_ICON_INFO,

    ];

    final public const FEATURE_AUTO_ASSIGN = 'autoAssignment';
    final public const FEATURE_EXTEND_CONTEXT_MENU = 'extendedContextMenu';
    final public const FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT = 'currentAssigneeHighlight';
    final public const FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET = 'clearCommentsOnStatusReset';

    final public const CACHE_IDENTIFIER = 'ximatypo3contentplanner';
}
