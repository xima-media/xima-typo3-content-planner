<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

class VisibilityUtility
{
    public static function checkContentStatusVisibility(): bool
    {
        // check permission
        if (!$GLOBALS['BE_USER']->isAdmin() && !$GLOBALS['BE_USER']->check('custom_options', 'tx_ximatypo3contentplanner:content-status')) {
            return false;
        }

        // check user setting
        if ($GLOBALS['BE_USER']->user['tx_ximatypo3contentplanner_hide'] === 1) {
            return false;
        }

        return true;
    }
}
