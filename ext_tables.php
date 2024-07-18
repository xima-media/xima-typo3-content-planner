<?php

declare(strict_types=1);

use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') or die();

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3contentplanner_hide'] = [
    'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
    'description' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide.description',
    'type' => 'check',
    'table' => 'be_users',
];

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3contentplanner_subscribe'] = [
    'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_subscribe',
    'description' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_subscribe.description',
    'type' => 'select',
    'table' => 'be_users',
    'items' => [
        '' => 'Never',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
    ],
];

$GLOBALS['TYPO3_USER_SETTINGS']['showitem'] .= ',
--div--;LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:content_planner,
    tx_ximatypo3contentplanner_hide,
    tx_ximatypo3contentplanner_subscribe,
    ';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']['tx_ximatypo3contentplanner'] = [
    'header' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:permission.group',
    'items' => [
        'content-status' => [
            'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:permission.content_status',
            'flag-black',
            'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:permission.content_status.description',
        ],
    ],
];
