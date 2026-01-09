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
