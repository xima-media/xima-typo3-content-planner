<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') || exit;

call_user_func(static function (): void {
    $temporaryColumns = [
        'tx_ximatypo3contentplanner_allowed_statuses' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_groups.tx_ximatypo3contentplanner_allowed_statuses',
            'description' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_groups.tx_ximatypo3contentplanner_allowed_statuses.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'foreign_table' => 'tx_ximatypo3contentplanner_domain_model_status',
                'foreign_table_where' => ' AND {#tx_ximatypo3contentplanner_domain_model_status}.{#hidden} = 0 AND {#tx_ximatypo3contentplanner_domain_model_status}.{#deleted} = 0 ORDER BY {#tx_ximatypo3contentplanner_domain_model_status}.{#sorting}',
                'size' => 5,
                'autoSizeMax' => 20,
            ],
        ],
        'tx_ximatypo3contentplanner_allowed_tables' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_groups.tx_ximatypo3contentplanner_allowed_tables',
            'description' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_groups.tx_ximatypo3contentplanner_allowed_tables.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\Data\StatusRegistry->getRecordTablesForTCA',
                'size' => 5,
                'autoSizeMax' => 10,
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('be_groups', $temporaryColumns);
    ExtensionManagementUtility::addToAllTCAtypes('be_groups', '--div--;LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:content_planner,tx_ximatypo3contentplanner_allowed_statuses,tx_ximatypo3contentplanner_allowed_tables');
});
