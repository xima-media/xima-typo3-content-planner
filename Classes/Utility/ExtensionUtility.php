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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\{ExtensionManagementUtility, GeneralUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_key_exists;
use function in_array;
use function is_string;

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
                Configuration::FIELD_STATUS => [
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
                Configuration::FIELD_ASSIGNEE => [
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
                Configuration::FIELD_COMMENTS => [
                    'label' => 'LLL:EXT:'.Configuration::EXT_KEY.
                        '/Resources/Private/Language/locallang_db.xlf:pages.tx_ximatypo3contentplanner_comments',
                    'config' => [
                        'foreign_field' => 'foreign_uid',
                        'foreign_default_sortby' => 'crdate DESC',
                        'foreign_table' => Configuration::TABLE_COMMENT,
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
            'showitem' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee',
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

        // A registration whose extension is gone (or is simply misspelled) would otherwise be
        // queried like any other record table and take the whole listing down with it.
        $additionalTables = array_filter(
            $additionalTables,
            static fn (mixed $table): bool => is_string($table) && isset($GLOBALS['TCA'][$table]),
        );

        $baseTables = ['pages'];

        if (self::isFilelistSupportEnabled()) {
            $baseTables[] = 'sys_file_metadata';
            $baseTables[] = Configuration::TABLE_FOLDER;
        }

        if (self::isContentElementSupportEnabled()) {
            $baseTables[] = 'tt_content';
        }

        return array_merge($baseTables, $additionalTables);
    }

    public static function isRegisteredRecordTable(string $table): bool
    {
        return in_array($table, self::getRecordTables(), true);
    }

    public static function isFilelistSupportEnabled(): bool
    {
        return self::isFeatureEnabled('enableFilelistSupport');
    }

    public static function isContentElementSupportEnabled(): bool
    {
        return self::isFeatureEnabled('enableContentElementSupport');
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

    /**
     * Polling interval in seconds for the backend toolbar notification center (issue #301).
     * `0` disables polling; the JS module then only refreshes on backend load.
     */
    public static function getNotificationPollInterval(): int
    {
        $interval = (int) self::getExtensionSetting(Configuration::CONF_NOTIFICATION_POLL_INTERVAL);

        return max(0, $interval);
    }

    public static function isNotificationDigestEmailEnabled(): bool
    {
        return self::isFeatureEnabled(Configuration::FEATURE_NOTIFICATION_DIGEST_EMAIL);
    }

    /**
     * Configured fallback used to build absolute backend deep links for the email digest
     * (issue #302): TYPO3 cannot reliably determine its own base URL from a CLI context, so
     * this extension configuration value is prepended to the (CLI-safe, relative)
     * {@see Routing\UrlUtility::getRecordLink()} result.
     * Empty when unconfigured - callers then fall back to a relative link.
     */
    public static function getNotificationDigestBackendBaseUrl(): string
    {
        return rtrim(self::getExtensionSetting(Configuration::CONF_NOTIFICATION_DIGEST_BACKEND_BASE_URL), '/');
    }

    /**
     * Retention threshold in days for *read* notifications (issue #304's
     * `content-planner:notification:cleanup` command). Falls back to the documented default of
     * 30 days when unset or blank, since an unconfigured `ExtensionConfiguration` entry (e.g. in
     * a test not exercising `extension:setup`) reads as an empty string, not the
     * `ext_conf_template.txt` default.
     */
    public static function getNotificationRetentionReadDays(): int
    {
        return self::getPositiveIntSettingOrDefault(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, 30);
    }

    /**
     * Retention threshold in days for *unread* notifications (issue #304's
     * `content-planner:notification:cleanup` command). Deliberately longer than the read
     * threshold by default: an unread notification has not yet been seen by its recipient and
     * gets more time before it is cleaned up. See {@see self::getNotificationRetentionReadDays()}.
     */
    public static function getNotificationRetentionUnreadDays(): int
    {
        return self::getPositiveIntSettingOrDefault(Configuration::CONF_NOTIFICATION_RETENTION_UNREAD_DAYS, 90);
    }

    public static function getTitleField(string $table): string
    {
        // Not every table declares a label — and a stale registration has no TCA at all.
        return $GLOBALS['TCA'][$table]['ctrl']['label'] ?? 'uid';
    }

    /**
     * @param array<string, mixed>|bool|null $record
     */
    public static function getTitle(string $key, array|bool|null $record): string
    {
        if ($record && array_key_exists($key, $record)) {
            return (string) $record[$key];
        }

        return BackendUtility::getNoRecordTitle();
    }

    /**
     * Retention settings are day counts that end up in a "delete everything older than now
     * minus N days" condition, so zero would mean "delete everything", including rows written
     * seconds ago. The extension configuration lets an administrator enter 0, so the floor is
     * enforced here rather than trusted: anything below a day falls back to the documented
     * default instead of silently emptying the table.
     */
    private static function getPositiveIntSettingOrDefault(string $key, int $default): int
    {
        $value = self::getExtensionSetting($key);
        $days = '' === $value ? $default : (int) $value;

        return $days >= 1 ? $days : $default;
    }
}
