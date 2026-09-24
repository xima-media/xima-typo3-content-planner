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

use Xima\XimaTypo3ContentPlanner\Controller\Backend\RecordModuleController;

return [
    // Top-level main module (no 'parent', no 'path'): the extension is not page-bound, so it
    // gets its own entry rather than nesting under "Web" - matches how TYPO3 core gives
    // cross-cutting areas (site, admin, system) their own top-level slot. Purely a container;
    // TYPO3 navigates to the first submodule automatically. Records is the first submodule
    // (CP-32, #404); Status (#411) follows without restructuring this entry.
    'content_planner' => [
        'labels' => 'content_planner.modules.main',
        'iconIdentifier' => 'dashboard-preset',
    ],
    'content_planner_records' => [
        'parent' => 'content_planner',
        'path' => '/module/content-planner/records',
        'iconIdentifier' => 'dashboard-status',
        'labels' => 'content_planner.modules.records',
        'routes' => [
            '_default' => [
                'target' => RecordModuleController::class.'::indexAction',
            ],
        ],
    ],
];
