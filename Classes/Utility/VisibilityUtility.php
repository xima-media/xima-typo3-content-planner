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

namespace Xima\XimaTypo3ContentPlanner\Utility;

/**
 * VisibilityUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class VisibilityUtility
{
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
