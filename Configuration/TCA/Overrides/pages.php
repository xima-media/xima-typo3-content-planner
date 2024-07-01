<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') or die('Access denied.');

call_user_func(function () {
    $temporaryColumns = [
        'tx_ximatypo3contentplanner_status' => [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_status',
            'config' => [
                'items' => [
                    ['-- stateless --', null],
                    ['LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:status.danger', 'danger', 'flag-red'],
                    ['LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:status.info', 'info', 'flag-blue'],
                    ['LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:status.warning', 'warning', 'flag-yellow'],
                    ['LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:status.success', 'success', 'flag-green'],
                ],
                'type' => 'select',
                'renderType' => 'selectSingle',
                'fieldWizard' => [
                    'selectIcons' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
        'tx_ximatypo3contentplanner_comments' => [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_comments',
            'config' => [
                'foreign_field' => 'foreign_uid',
                'foreign_sortby' => 'sorting',
                'foreign_table' => 'tx_ximatypo3contentplanner_comment',
                'foreign_table_field' => 'foreign_table',
                'type' => 'inline',
                'appearance' => [
                    'collapseAll' => true,
                    'expandSingle' => true,
                    'useSortable' => true,
                    'enabledControls' => [
                        'dragdrop' => true,
                        'info' => false,
                    ],
                    'showSynchronizationLink' => true,
                    'showAllLocalizationLink' => true,
                    'showPossibleLocalizationRecords' => true,
                ],
            ],
        ],
        'tx_ximatypo3contentplanner_assignee' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_assignee',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['-- Not assigned --', 0],
                ],
                'foreign_table' => 'be_users',
                'resetSelection' => true,
                'eval' => 'null',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
    ];

    $GLOBALS['TCA']['pages']['palettes']['tx_ximatypo3contentplanner'] = [
        'showitem' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee,--linebreak--,tx_ximatypo3contentplanner_comments',
    ];
    ExtensionManagementUtility::addTCAcolumns(
        'pages',
        $temporaryColumns
    );
    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        '--div--;Content  Planner,--palette--;;tx_ximatypo3contentplanner'
    );
});
