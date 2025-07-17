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
        'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:status',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'adminOnly' => true,
        'rootLevel' => 1,
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'default_sortby' => 'sorting ASC',
        'sortby' => 'sorting',
        'typeicon_classes' => [
            'default' => 'flag-gray',
        ],
        'searchFields' => 'title',
    ],
    'types' => [
        '0' => [
            'showitem' => 'title,icon,color',
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
        'title' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_status.title',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'icon' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_status.icon',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\StatusRegistry->getStatusIcons',
                'minitems' => 1,
                'maxitems' => 1,
                'size' => 1,
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
        'color' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:tx_ximatypo3contentplanner_status.color',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\StatusRegistry->getStatusColors',
                'minitems' => 1,
                'maxitems' => 1,
                'size' => 1,
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
    ],
];
