<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

class ExtensionUtility
{
    public static function addContentPlannerTabToTCA(string $table): void
    {
        ExtensionManagementUtility::addTCAcolumns(
            $table,
            [
                'tx_ximatypo3contentplanner_status' => [
                    'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_status',
                    'config' => [
                        'items' => [
                            ['-- stateless --', null],
                        ],
                        'nullable' => true,
                        'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\StatusRegistry->getStatus',
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
                        'nullable' => true,
                        'items' => [
                            ['-- Not assigned --', null],
                        ],
                        'foreign_table' => 'be_users',
                        'resetSelection' => true,
                        'eval' => 'null',
                        'minitems' => 0,
                        'maxitems' => 1,
                    ],
                ],
            ]
        );

        $GLOBALS['TCA'][$table]['palettes']['tx_ximatypo3contentplanner'] = [
            'showitem' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee,--linebreak--,tx_ximatypo3contentplanner_comments',
        ];

        ExtensionManagementUtility::addToAllTCAtypes(
            $table,
            '--div--;Content  Planner,--palette--;;tx_ximatypo3contentplanner'
        );
    }

    public static function getRecordTables(): array
    {
        return array_merge(['pages'], $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']);
    }

    public static function isRegisteredRecordTable(string $table): bool
    {
        return in_array($table, self::getRecordTables());
    }
}
