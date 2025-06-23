<?php

return [
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'access' => 'public',
        'target' => \Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class . '::messageAction',
    ],
];
