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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\{ExtensionManagementUtility, GeneralUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_key_exists;
use function in_array;

/**
 * ExtensionUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ExtensionUtility
{
    public static function addContentPlannerTabToTCA(string $table): void
    {
        ExtensionManagementUtility::addTCAcolumns(
            $table,
            [
                'tx_ximatypo3contentplanner_status' => [
                    'label' => 'LLL:EXT:'.Configuration::EXT_KEY.
                        '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_status',
                    'config' => [
                        'items' => [
                            ['label' => '-- stateless --', 'value' => null],
                        ],
                        'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\Data\StatusRegistry->getStatus',
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
                'tx_ximatypo3contentplanner_assignee' => [
                    'exclude' => 1,
                    'label' => 'LLL:EXT:'.Configuration::EXT_KEY.
                        '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_assignee',
                    'config' => [
                        'type' => 'select',
                        'renderType' => 'selectSingle',
                        'items' => [
                            [
                                'label' => 'LLL:EXT:'.Configuration::EXT_KEY.
                                    '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_assignee.empty',
                                'value' => null,
                            ],
                        ],
                        'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\Data\StatusRegistry->getAssignableUsers',
                        'resetSelection' => true,
                        'minitems' => 0,
                        'maxitems' => 1,
                        'nullable' => true,
                    ],
                ],
                'tx_ximatypo3contentplanner_comments' => [
                    'label' => 'LLL:EXT:'.Configuration::EXT_KEY.
                        '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_comments',
                    'config' => [
                        'foreign_field' => 'foreign_uid',
                        'foreign_default_sortby' => 'crdate',
                        'foreign_table' => 'tx_ximatypo3contentplanner_comment',
                        'foreign_table_field' => 'foreign_table',
                        'type' => 'inline',
                        'appearance' => [
                            'collapseAll' => true,
                            'expandSingle' => true,
                            'useSortable' => false,
                        ],
                    ],
                ],
            ],
        );

        $GLOBALS['TCA'][$table]['palettes']['tx_ximatypo3contentplanner'] = [
            'showitem' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee,--linebreak--,tx_ximatypo3contentplanner_comments',
        ];

        ExtensionManagementUtility::addToAllTCAtypes(
            $table,
            '--div--;Content Planner,--palette--;;tx_ximatypo3contentplanner',
        );
    }

    /**
     * @return string[]
     */
    public static function getRecordTables(): array
    {
        $additionalTables = (array) (
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']
                ?? []
        );

        return array_merge(['pages'], $additionalTables);
    }

    public static function isRegisteredRecordTable(string $table): bool
    {
        return in_array($table, self::getRecordTables(), true);
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get(Configuration::EXT_KEY);

        return array_key_exists($feature, $configuration)
            && (bool) $configuration[$feature];
    }

    public static function getExtensionSetting(string $feature): string
    {
        $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get(Configuration::EXT_KEY);

        return $configuration[$feature] ?? '';
    }

    public static function getTitleField(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['label'];
    }

    /**
     * @param array<string, mixed>|bool|null $record
     */
    public static function getTitle(string $key, array|bool|null $record): string
    {
        if ($record) {
            return array_key_exists($key, $record)
                ? $record[$key]
                : BackendUtility::getNoRecordTitle();
        }

        return BackendUtility::getNoRecordTitle();
    }
}
