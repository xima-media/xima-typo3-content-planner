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

use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') || exit;

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3contentplanner_hide'] = [
    'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
    'description' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide.description',
    'type' => 'check',
    'table' => 'be_users',
];

$GLOBALS['TYPO3_USER_SETTINGS']['showitem'] .= ',
--div--;LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:content_planner,
    tx_ximatypo3contentplanner_hide,
    ';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_ximatypo3contentplanner'] = [
    'header' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.group',
    'items' => [
        'content-status' => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.content_status',
            'flag-black',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.content_status.description',
        ],
    ],
];
