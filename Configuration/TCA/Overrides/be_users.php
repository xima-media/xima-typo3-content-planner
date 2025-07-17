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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') or die();

call_user_func(function () {
    $temporaryColumns = [
        'tx_ximatypo3contentplanner_hide' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
            'description' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => 'hide',
                    ],
                ],
            ],
        ],
    ];
    $GLOBALS['TCA']['pages']['palettes']['tx_ximatypo3contentplanner'] = [
        'showitem' => 'tx_ximatypo3contentplanner_hide',
    ];

    ExtensionManagementUtility::addTCAcolumns('be_users', $temporaryColumns);
    ExtensionManagementUtility::addToAllTCAtypes('be_users', '--div--;Content  Planner,--palette--;;tx_ximatypo3contentplanner');
});
