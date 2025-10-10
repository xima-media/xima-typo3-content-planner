<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') || exit;

call_user_func(function () {
    $temporaryColumns = [
        'tx_ximatypo3contentplanner_hide' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
            'description' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide.description',
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
