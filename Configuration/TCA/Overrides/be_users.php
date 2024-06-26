<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaContentPlanner\Configuration;

defined('TYPO3') or die();

call_user_func(function () {
    $temporaryColumns = [
        'tx_ximacontentplanner_hide' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximacontentplanner_hide',
            'description' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximacontentplanner_hide.description',
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
    $GLOBALS['TCA']['pages']['palettes']['tx_ximacontentplanner'] = [
        'showitem' => 'tx_ximacontentplanner_hide',
    ];

    ExtensionManagementUtility::addTCAcolumns('be_users', $temporaryColumns);
    ExtensionManagementUtility::addToAllTCAtypes('be_users', '--div--;Content  Planner,--palette--;;tx_ximacontentplanner');
});
