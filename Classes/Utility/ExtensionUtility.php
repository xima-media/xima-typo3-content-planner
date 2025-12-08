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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\SystemResource\Publishing\{SystemResourcePublisherInterface, UriGenerationOptions};
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\Utility\{ExtensionManagementUtility, GeneralUtility, PathUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_key_exists;
use function in_array;
use function sprintf;

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
                        'itemsProcFunc' => 'Xima\XimaTypo3ContentPlanner\Utility\StatusRegistry->getAssignableUsers',
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

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws InvalidFileException
     */
    public static function getCssTag(
        string $cssFileLocation,
        array $attributes,
    ): string {
        return sprintf(
            '<link %s />',
            GeneralUtility::implodeAttributes([
                ...$attributes,
                'rel' => 'stylesheet',
                'media' => 'all',
                'href' => self::getPublicResourcePath($cssFileLocation),
            ], true),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws InvalidFileException
     */
    public static function getJsTag(
        string $jsFileLocation,
        array $attributes,
    ): string {
        return sprintf(
            '<script type="module" %s></script>',
            GeneralUtility::implodeAttributes([
                ...$attributes,
                'src' => self::getPublicResourcePath($jsFileLocation),
            ], true),
        );
    }

    /**
     * Get public resource path with TYPO3 13/14 compatibility.
     * Uses SystemResourceFactory for TYPO3 14+, PathUtility for TYPO3 13.
     */
    private static function getPublicResourcePath(string $resourcePath): string
    {
        if (VersionHelper::is14OrHigher()) {
            /** @var SystemResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(SystemResourceFactory::class);
            /** @var SystemResourcePublisherInterface $resourcePublisher */
            $resourcePublisher = GeneralUtility::makeInstance(SystemResourcePublisherInterface::class);
            $resource = $resourceFactory->createPublicResource($resourcePath);
            /** @var ServerRequestInterface|null $request */
            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

            return (string) $resourcePublisher->generateUri(
                $resource,
                $request,
                new UriGenerationOptions(absoluteUri: false),
            );
        }

        // TYPO3 13 fallback - deprecated in v14, will be removed in v15
        // @phpstan-ignore staticMethod.deprecated
        return PathUtility::getPublicResourceWebPath($resourcePath);
    }
}
