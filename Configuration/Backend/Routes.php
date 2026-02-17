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
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'access' => 'public',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class.'::messageAction',
    ],
    'ximatypo3contentplanner_folder_status_update' => [
        'path' => '/content-planner/folder/status/update',
        'access' => 'public',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\FolderController::class.'::updateStatusAction',
    ],
    'ximatypo3contentplanner_share' => [
        'path' => '/content-planner/share',
        'access' => 'public',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::shareAction',
    ],
];
