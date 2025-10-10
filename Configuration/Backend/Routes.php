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

return [
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'access' => 'public',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class.'::messageAction',
    ],
];
