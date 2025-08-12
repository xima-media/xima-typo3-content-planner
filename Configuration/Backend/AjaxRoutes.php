<?php

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

return [
    'ximatypo3contentplanner_filterrecords' => [
        'path' => '/content-planner/records',
        'target' => \Xima\XimaTypo3ContentPlanner\Controller\RecordController::class . '::filterAction',
    ],
    'ximatypo3contentplanner_comments' => [
        'path' => '/content-planner/comments',
        'target' => \Xima\XimaTypo3ContentPlanner\Controller\RecordController::class . '::commentsAction',
    ],
    'ximatypo3contentplanner_assignees' => [
        'path' => '/content-planner/assignees',
        'target' => \Xima\XimaTypo3ContentPlanner\Controller\RecordController::class . '::assigneeSelectionAction',
    ],
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'target' => \Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class . '::messageAction',
    ],
];
