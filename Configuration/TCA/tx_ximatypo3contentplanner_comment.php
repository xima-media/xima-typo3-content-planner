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

use Xima\XimaTypo3ContentPlanner\Configuration;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comment',
        'label' => 'content',
        'label_alt' => 'author, content',
        'delete' => 'deleted',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'hideTable' => true,
        'typeicon_classes' => [
            'default' => 'content-message',
        ],
        'searchFields' => 'title,text',
        'default_sortby' => 'crdate DESC',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
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
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_comment.content',
            'config' => [
                'enableRichtext' => true,
                'richtextConfiguration' => 'comments',
                'type' => 'text',
                'eval' => 'trim',
                'cols' => 40,
                'rows' => 5,
                'max' => 500,
                'required' => true,
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
