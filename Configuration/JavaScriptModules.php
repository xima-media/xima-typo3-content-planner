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

use Xima\XimaTypo3ContentPlanner\Configuration;

return [
    'dependencies' => ['core', 'backend'],
    'tags' => [
        'backend.contextmenu',
    ],
    'imports' => [
        Configuration::JAVASCRIPT_MODULE_PREFIX => 'EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/',
    ],
];
