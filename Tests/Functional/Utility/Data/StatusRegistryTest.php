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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Data;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Data\StatusRegistry;

use function count;

/**
 * StatusRegistryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusRegistryTest extends AbstractFunctionalTestCase
{
    private StatusRegistry $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();
        $this->subject = new StatusRegistry();
    }

    #[Test]
    public function getStatusAddsAllStatusesAsItems(): void
    {
        $config = ['items' => []];
        $this->subject->getStatus($config);

        self::assertCount(3, $config['items']);
        self::assertSame('Draft', $config['items'][0][0]);
        self::assertSame(1, $config['items'][0][1]);
        self::assertSame('flag-blue', $config['items'][0][2]);
    }

    #[Test]
    public function getStatusIconsAddsAllIcons(): void
    {
        $config = ['items' => []];
        $this->subject->getStatusIcons($config);

        self::assertCount(count(Configuration\Icons::STATUS_ICONS), $config['items']);
        self::assertSame('flag', $config['items'][0][0]);
        self::assertSame('flag-black', $config['items'][0][2]);
    }

    #[Test]
    public function getStatusColorsAddsAllColors(): void
    {
        $config = ['items' => []];
        $this->subject->getStatusColors($config);

        self::assertCount(count(Configuration\Colors::STATUS_COLORS), $config['items']);
        self::assertSame('black', $config['items'][0][0]);
        self::assertSame('color-black', $config['items'][0][2]);
    }

    #[Test]
    public function getAssignableUsersAddsUsersWithLabelAndValue(): void
    {
        $config = ['items' => []];
        $this->subject->getAssignableUsers($config);

        self::assertIsArray($config['items']);
        foreach ($config['items'] as $item) {
            self::assertArrayHasKey('label', $item);
            self::assertArrayHasKey('value', $item);
        }
    }

    #[Test]
    public function getRecordTablesForTcaAddsRegisteredTables(): void
    {
        $config = ['items' => []];
        $this->subject->getRecordTablesForTCA($config);

        $values = array_map(static fn (array $item): string => $item['value'], $config['items']);
        self::assertContains('pages', $values);
        self::assertContains('tt_content', $values);
        self::assertContains('sys_file_metadata', $values);
    }

    #[Test]
    public function getRegisteredTablesIncludesCoreTables(): void
    {
        $tables = StatusRegistry::getRegisteredTables();

        self::assertContains('pages', $tables);
        self::assertContains('tt_content', $tables);
        self::assertContains('sys_file_metadata', $tables);
    }

    #[Test]
    public function getRegisteredTablesMergesAdditionalTables(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tx_news_domain_model_news'];

        $tables = StatusRegistry::getRegisteredTables();

        self::assertContains('tx_news_domain_model_news', $tables);

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']);
    }
}
