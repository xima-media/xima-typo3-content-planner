<?php

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
];
