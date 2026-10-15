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
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\StatusOverviewDataProvider;

/**
 * StatusOverviewDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusOverviewDataProviderTest extends AbstractFunctionalTestCase
{
    private StatusOverviewDataProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->subject = new StatusOverviewDataProvider(
            $this->get(StatusRepository::class),
            $this->get(RecordRepository::class),
        );
    }

    #[Test]
    public function getChartDataReturnsOneSegmentPerStatusOnRepeatedCalls(): void
    {
        $this->subject->getChartData();

        self::assertCount(3, $this->subject->getChartData()['labels']);
    }

    #[Test]
    public function getChartDataIsEmptyWhenContentPlannerIsHiddenForTheUser(): void
    {
        $GLOBALS['BE_USER']->user['tx_ximatypo3contentplanner_hide'] = 1;

        $chartData = $this->subject->getChartData();

        self::assertSame([], $chartData['labels']);
        self::assertSame([], $chartData['datasets'][0]['data']);
    }
}
