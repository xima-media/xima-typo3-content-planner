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

use Xima\XimaTypo3ContentPlanner\Configuration;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_watcher',
        'label' => 'tablename',
        'label_alt' => 'record_uid, backend_user',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'hideTable' => true,
        'rootLevel' => -1,
        'typeicon_classes' => [
            'default' => 'actions-user',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'tablename, record_uid, backend_user, mode, source',
        ],
    ],
    'columns' => [
        'tablename' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_watcher.tablename',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'readOnly' => true,
            ],
        ],
        'record_uid' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_watcher.record_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'backend_user' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_watcher.backend_user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'be_users',
                'readOnly' => true,
            ],
        ],
        'mode' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_watcher.mode',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 32,
                'readOnly' => true,
            ],
        ],
        'source' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_watcher.source',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 32,
                'readOnly' => true,
            ],
        ],
    ],
];
