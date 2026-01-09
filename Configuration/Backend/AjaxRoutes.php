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
    'ximatypo3contentplanner_filterrecords' => [
        'path' => '/content-planner/records',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::filterAction',
    ],
    'ximatypo3contentplanner_comments' => [
        'path' => '/content-planner/comments',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::commentsAction',
    ],
    'ximatypo3contentplanner_assignees' => [
        'path' => '/content-planner/assignees',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::assigneeSelectionAction',
    ],
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class.'::messageAction',
    ],
];
