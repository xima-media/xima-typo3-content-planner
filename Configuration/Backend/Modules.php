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

use Xima\XimaTypo3ContentPlanner\Controller\Backend\{RecordModuleController, StatusModuleController};

return [
    // Top-level main module (no 'parent', no 'path'): the extension is not page-bound, so it
    // gets its own entry rather than nesting under "Web" - matches how TYPO3 core gives
    // cross-cutting areas (site, admin, system) their own top-level slot. Purely a container;
    // TYPO3 navigates to the first submodule automatically. Records is the first submodule
    // (CP-32, #404); Status (#411) follows without restructuring this entry.
    // 'labels' deliberately uses the older 'LLL:EXT:.../file.xlf' form (mlang_tabs_tab etc.
    // trans-unit ids), not the newer dotted-domain shorthand ('ext.modules.name') - v13's
    // BaseModule only understands the LLL: form, the shorthand silently resolves to a blank
    // menu label there (CP-32, #404 requires both v13 and v14).
    'content_planner' => [
        'labels' => 'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/Modules/main.xlf',
        'iconIdentifier' => 'dashboard-preset',
    ],
    'content_planner_records' => [
        'parent' => 'content_planner',
        'path' => '/module/content-planner/records',
        'iconIdentifier' => 'dashboard-status',
        'labels' => 'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/Modules/records.xlf',
        'routes' => [
            '_default' => [
                'target' => RecordModuleController::class.'::indexAction',
            ],
        ],
    ],
    'content_planner_status' => [
        'parent' => 'content_planner',
        // Admin-only, matching the status table's own TCA (adminOnly => true, #411) - the
        // controller re-checks this itself as defense in depth, since 'access' alone only hides
        // the menu entry and the route, not a guarantee callers should rely on exclusively.
        'access' => 'admin',
        'path' => '/module/content-planner/status',
        'iconIdentifier' => 'flag-gray',
        'labels' => 'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/Modules/status.xlf',
        'routes' => [
            '_default' => [
                'target' => StatusModuleController::class.'::indexAction',
            ],
        ],
    ],
];
