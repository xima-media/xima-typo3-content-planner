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

defined('TYPO3') || exit('Access denied.');

call_user_func(function (): void {
    if (Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::isFilelistSupportEnabled()) {
        Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::addContentPlannerTabToTCA('sys_file_metadata');
    }
});
