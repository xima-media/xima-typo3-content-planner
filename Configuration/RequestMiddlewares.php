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

return [
    'backend' => [
        'xima/content-planner-content-element' => [
            'target' => Xima\XimaTypo3ContentPlanner\Middleware\BackendContentModifierMiddleware::class,
            'before' => [
                'typo3/cms-core/response-propagation',
            ],
            'after' => [
                'typo3/cms-backend/site-resolver',
            ],
        ],
    ],
];
