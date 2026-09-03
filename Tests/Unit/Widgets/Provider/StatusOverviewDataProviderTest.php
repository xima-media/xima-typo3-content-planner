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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Widgets\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\StatusOverviewDataProvider;

/**
 * StatusOverviewDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusOverviewDataProviderTest extends TestCase
{
    private StatusRepository&\PHPUnit\Framework\MockObject\MockObject $statusRepository;
    private RecordRepository&\PHPUnit\Framework\MockObject\MockObject $recordRepository;
    private StatusOverviewDataProvider $subject;

    protected function setUp(): void
    {
        $this->statusRepository = $this->createMock(StatusRepository::class);
        $this->recordRepository = $this->createMock(RecordRepository::class);
        $this->subject = new StatusOverviewDataProvider($this->statusRepository, $this->recordRepository);
    }

    #[Test]
    public function getChartDataReturnsLabelsColorsAndCountsForEachStatus(): void
    {
        $draft = $this->createStatus(1, 'Draft', 'blue');
        $done = $this->createStatus(2, 'Done', 'green');

        $this->statusRepository->method('findAll')->willReturn([$draft, $done]);
        $this->recordRepository->expects(self::once())
            ->method('countRecordsByStatus')
            ->willReturn([1 => 5, 2 => 2]);

        $chartData = $this->subject->getChartData();

        self::assertSame(['Draft', 'Done'], $chartData['labels']);
        self::assertSame([5, 2], $chartData['datasets'][0]['data']);
        self::assertSame(['rgb(100,187,200)', 'rgb(106,158,113)'], $chartData['datasets'][0]['backgroundColor']);
        self::assertSame(0, $chartData['datasets'][0]['border']);
    }

    #[Test]
    public function getChartDataReturnsEmptyStructureWhenNoStatusesExist(): void
    {
        $this->statusRepository->method('findAll')->willReturn([]);
        $this->recordRepository->method('countRecordsByStatus')->willReturn([]);

        $chartData = $this->subject->getChartData();

        self::assertSame([], $chartData['labels']);
        self::assertSame([], $chartData['datasets'][0]['data']);
        self::assertSame([], $chartData['datasets'][0]['backgroundColor']);
    }

    #[Test]
    public function countPageStatusReturnsCountForKnownStatus(): void
    {
        $this->recordRepository->method('countRecordsByStatus')->willReturn([3 => 7]);

        self::assertSame(7, $this->subject->countPageStatus(3));
    }

    #[Test]
    public function countPageStatusReturnsZeroForUnknownStatus(): void
    {
        $this->recordRepository->method('countRecordsByStatus')->willReturn([3 => 7]);

        self::assertSame(0, $this->subject->countPageStatus(99));
    }

    #[Test]
    public function countPageStatusReturnsZeroWhenNoStatusGiven(): void
    {
        $this->recordRepository->method('countRecordsByStatus')->willReturn([3 => 7]);

        self::assertSame(0, $this->subject->countPageStatus());
    }

    #[Test]
    public function countPageStatusMemoizesRepositoryCallAcrossMultipleInvocations(): void
    {
        $this->recordRepository->expects(self::once())
            ->method('countRecordsByStatus')
            ->willReturn([1 => 4]);

        self::assertSame(4, $this->subject->countPageStatus(1));
        self::assertSame(4, $this->subject->countPageStatus(1));
        self::assertSame(0, $this->subject->countPageStatus(2));
    }

    #[Test]
    public function getLanguageServiceReturnsGlobalLanguageService(): void
    {
        $languageService = $this->createMock(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;

        try {
            $method = new ReflectionMethod($this->subject, 'getLanguageService');

            self::assertSame($languageService, $method->invoke($this->subject));
        } finally {
            unset($GLOBALS['LANG']);
        }
    }

    private function createStatus(int $uid, string $title, string $color): Status
    {
        return new Status(uid: $uid, title: $title, icon: '', color: $color);
    }
}
