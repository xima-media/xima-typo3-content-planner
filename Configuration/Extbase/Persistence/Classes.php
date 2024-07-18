<?php

use Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser;

return [
    BackendUser::class => [
        'tableName' => 'be_users',
        'properties' => [
            'hide' => [
                'fieldName' => 'tx_ximatypo3contentplanner_hide',
            ],
            'subscribe' => [
                'fieldName' => 'tx_ximatypo3contentplanner_subscribe',
            ],
            'lastMail' => [
                'fieldName' => 'tx_ximatypo3contentplanner_last_mail',
            ],
        ],
    ],
];
