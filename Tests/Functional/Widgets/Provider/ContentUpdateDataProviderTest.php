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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Widgets\Provider;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\HistoryItem;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentUpdateDataProvider;

/**
 * ContentUpdateDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentUpdateDataProviderTest extends AbstractFunctionalTestCase
{
    private ContentUpdateDataProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] ??= [];
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_history.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', ['id' => 1]);
        $this->subject = new ContentUpdateDataProvider($this->get(ConnectionPool::class));
    }

    #[Test]
    public function getItemsReturnsContentPlannerHistoryItems(): void
    {
        $items = $this->subject->getItems();

        self::assertNotEmpty($items);
        foreach ($items as $item) {
            self::assertInstanceOf(HistoryItem::class, $item);
            self::assertNotSame('', $item->getHistoryData());
        }
    }

    #[Test]
    public function fetchUpdateDataReturnsEmptyArrayForFutureTimestamp(): void
    {
        $items = $this->subject->fetchUpdateData(tstamp: 99999999, maxItems: 15);

        self::assertSame([], $items);
    }

    #[Test]
    public function fetchUpdateDataReturnsArrayWithoutMaxItems(): void
    {
        $items = $this->subject->fetchUpdateData();

        self::assertNotEmpty($items);
    }
}
