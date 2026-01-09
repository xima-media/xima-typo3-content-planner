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
        'title' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comment',
        'label' => 'content',
        'label_alt' => 'author, content',
        'delete' => 'deleted',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'hideTable' => true,
        'rootLevel' => -1,
        'typeicon_classes' => [
            'default' => 'content-message',
        ],
        'default_sortby' => 'crdate DESC',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'versioningWS' => true,
    ],
    'types' => [
        '0' => [
            'showitem' => 'content',
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'content' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_comment.content',
            'config' => [
                'enableRichtext' => true,
                'richtextConfiguration' => 'comments',
                'type' => 'text',
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 5,
                'max' => 500,
                'required' => true,
                'searchable' => false,
            ],
        ],
        'author' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'foreign_table' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'foreign_uid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'pid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'edited' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'resolved_date' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'resolved_user' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'todo_resolved' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'todo_total' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
