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
        'title' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_folder',
        'label' => 'folder_identifier',
        'delete' => 'deleted',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'hideTable' => true,
        'rootLevel' => 1,
        'typeicon_classes' => [
            'default' => 'apps-filetree-folder-default',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'folder_identifier, storage_uid, --div--;Content Planner, tx_ximatypo3contentplanner_status, tx_ximatypo3contentplanner_assignee, tx_ximatypo3contentplanner_comments',
        ],
    ],
    'columns' => [
        'folder_identifier' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_folder.folder_identifier',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'readOnly' => true,
            ],
        ],
        'storage_uid' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_folder.storage_uid',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'tx_ximatypo3contentplanner_status' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_status',
            'config' => [
                'items' => [
                    ['label' => '-- stateless --', 'value' => null],
                ],
                'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\Data\StatusRegistry->getStatus',
                'type' => 'select',
                'renderType' => 'selectSingle',
                'resetSelection' => true,
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
                'nullable' => true,
            ],
        ],
        'tx_ximatypo3contentplanner_assignee' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_assignee',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_assignee.empty',
                        'value' => null,
                    ],
                ],
                'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\Data\StatusRegistry->getAssignableUsers',
                'resetSelection' => true,
                'minitems' => 0,
                'maxitems' => 1,
                'nullable' => true,
            ],
        ],
        'tx_ximatypo3contentplanner_comments' => [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_comments',
            'config' => [
                'foreign_field' => 'foreign_uid',
                'foreign_default_sortby' => 'crdate',
                'foreign_table' => 'tx_ximatypo3contentplanner_comment',
                'foreign_table_field' => 'foreign_table',
                'type' => 'inline',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'useSortable' => false,
                ],
            ],
        ],
    ],
];
