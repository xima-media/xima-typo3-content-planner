<?php

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
        'tx_ximatypo3contentplanner_subscribe' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_subscribe',
            'description' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_subscribe.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['Never', ''],
                    ['Daily', 'daily'],
                    ['Weekly', 'weekly'],
                ],
            ],
        ],
    ];
    $GLOBALS['TCA']['pages']['palettes']['tx_ximatypo3contentplanner'] = [
        'showitem' => 'tx_ximatypo3contentplanner_hide, tx_ximatypo3contentplanner_subscribe',
    ];

    ExtensionManagementUtility::addTCAcolumns('be_users', $temporaryColumns);
    ExtensionManagementUtility::addToAllTCAtypes('be_users', '--div--;Content  Planner,--palette--;;tx_ximatypo3contentplanner');
});
