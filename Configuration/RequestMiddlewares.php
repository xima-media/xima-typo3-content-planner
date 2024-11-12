<?php

return [
    'backend' => [
        'xima/content-planner-content-element' => [
            'target' => \Xima\XimaTypo3ContentPlanner\Middleware\BackendContentModifierMiddleware::class,
            'after' => [
                'typo3/cms-core/response-propagation',
            ],
        ],
    ],
];
