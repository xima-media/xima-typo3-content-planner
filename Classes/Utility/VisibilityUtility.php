<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Utility;

class VisibilityUtility
{
    public static function checkContentStatusVisibility(): bool
    {
        // check permission
        if (!$GLOBALS['BE_USER']->isAdmin() && !$GLOBALS['BE_USER']->check('custom_options', 'tx_ximacontentplanner:content-status')) {
            return false;
        }

        // check user setting
        if ($GLOBALS['BE_USER']->user['tx_ximacontentplanner_hide'] === 1) {
            return false;
        }

        return true;
    }
}
