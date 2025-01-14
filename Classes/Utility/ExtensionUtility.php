<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
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
                            ['label' => '-- stateless --', 'value' => null],
                        ],
                        'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\StatusRegistry->getStatus',
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'resetSelection' => true,
                        'fieldWizard' => [
                            'selectIcons' => [
                                'disabled' => false,
                            ],
                        ],
                        'nullable' => true,
                    ],
                ],
                'tx_ximatypo3contentplanner_comments' => [
                    'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_comments',
                    'config' => [
                        'foreign_field' => 'foreign_uid',
                        'foreign_sortby' => 'crdate',
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
                            ['label' => '-- Not assigned --', 'value' => null],
                        ],
                        'foreign_table' => 'be_users',
                        'resetSelection' => true,
                        'minitems' => 0,
                        'maxitems' => 1,
                        'nullable' => true,
                    ],
                ],
            ]
        );

        $GLOBALS['TCA'][$table]['palettes']['tx_ximatypo3contentplanner'] = [
            'showitem' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee,--linebreak--,tx_ximatypo3contentplanner_comments',
        ];

        ExtensionManagementUtility::addToAllTCAtypes(
            $table,
            '--div--;Content Planner,--palette--;;tx_ximatypo3contentplanner'
        );
    }

    public static function getRecordTables(): array
    {
        return array_merge(
            ['pages'],
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']
        );
    }

    public static function isRegisteredRecordTable(string $table): bool
    {
        return in_array($table, self::getRecordTables());
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(Configuration::EXT_KEY);
        return array_key_exists($feature, $configuration) && $configuration[$feature];
    }

    public static function getTitleField(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['label'];
    }

    public static function getTitle(string $key, array|bool|null $record): string
    {
        return $record ? (array_key_exists($key, $record) ? $record[$key] : BackendUtility::getNoRecordTitle()) : BackendUtility::getNoRecordTitle();
    }

    public static function getCssTag(string $cssFileLocation, array $attributes): string
    {
        return sprintf(
            '<link %s />',
            GeneralUtility::implodeAttributes([
                ...$attributes,
                'rel' => 'stylesheet',
                'media' => 'all',
                'href' => PathUtility::getPublicResourceWebPath($cssFileLocation),
            ], true)
        );
    }
}
