<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaContentPlanner\Configuration;

defined('TYPO3') or die('Access denied.');

call_user_func(function () {
    /**
     * Define custom fields
     */
    $temporaryColumns = [
        'tx_ximacontentplanner_status' => [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximacontentplanner_status',
            'config' => [
                'items' => [
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
        'tx_ximacontentplanner_comments' => [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximacontentplanner_comments',
            'config' => [
                'foreign_field' => 'foreign_uid',
                'foreign_sortby' => 'sorting',
                'foreign_table' => 'tx_ximacontentplanner_comment',
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
        'tx_ximacontentplanner_assignee' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximacontentplanner_assignee',
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

    $GLOBALS['TCA']['pages']['palettes']['tx_ximacontentplanner'] = [
        'showitem' => 'tx_ximacontentplanner_status,tx_ximacontentplanner_assignee,--linebreak--,tx_ximacontentplanner_comments',
    ];
    ExtensionManagementUtility::addTCAcolumns(
        'pages',
        $temporaryColumns
    );
    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        '--div--;Content  Planner,--palette--;;tx_ximacontentplanner'
    );
});
