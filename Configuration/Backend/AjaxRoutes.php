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
    'ximatypo3contentplanner_folder_status_update' => [
        'path' => '/content-planner/folder/status',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\FolderController::class.'::updateStatusAction',
    ],
    'ximatypo3contentplanner_folder_status_get' => [
        'path' => '/content-planner/folder/status/get',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\FolderController::class.'::getStatusAction',
    ],
    'ximatypo3contentplanner_filelist_status_options' => [
        'path' => '/content-planner/filelist/status-options',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\FolderController::class.'::getStatusOptionsAction',
    ],
    'ximatypo3contentplanner_file_status_update' => [
        'path' => '/content-planner/file/status',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\FolderController::class.'::updateFileStatusAction',
    ],
];
