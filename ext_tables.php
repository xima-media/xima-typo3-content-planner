<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3contentplanner_hide'] = [
    'label' => 'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
    'type' => 'check',
    'table' => 'be_users',
];
ExtensionManagementUtility::addFieldsToUserSettings(
    'tx_ximatypo3contentplanner_hide',
    'after:emailMeAtLogin',
);

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_ximatypo3contentplanner'] = [
    'header' => 'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_db.xlf:permission.group',
    'items' => [
        'content-status' => [
            'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_db.xlf:permission.content_status',
            'flag-black',
            'LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_db.xlf:permission.content_status.description',
        ],
    ],
];
