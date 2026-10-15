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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Domain\Model\RecordPreset;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Service\RecordPresetService;

/**
 * RecordPresetServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordPresetServiceTest extends TestCase
{
    #[Test]
    public function countsTheRecordsAssignedToTheUser(): void
    {
        $repository = $this->createMock(RecordRepository::class);
        $repository->expects(self::once())->method('findAllByFilter')
            ->with(null, null, 7, null, null, 999, false)
            ->willReturn(new PaginatedResult([['uid' => 1], ['uid' => 2]], false));

        self::assertSame(['count' => 2, 'countLabel' => '2'], (new RecordPresetService($repository))->count(RecordPreset::Assignee, 7));
    }

    #[Test]
    public function countsTheRecordsWithOpenTodos(): void
    {
        $repository = $this->createMock(RecordRepository::class);
        $repository->expects(self::once())->method('findAllByFilter')
            ->with(null, null, null, null, true, 999, false)
            ->willReturn(new PaginatedResult([['uid' => 1]], false));

        self::assertSame(['count' => 1, 'countLabel' => '1'], (new RecordPresetService($repository))->count(RecordPreset::Todo, 7));
    }

    #[Test]
    public function countsTheRecordsWithOpenComments(): void
    {
        $repository = $this->createMock(RecordRepository::class);
        $repository->expects(self::once())->method('findAllByFilter')
            ->with(null, null, null, null, null, 999, true)
            ->willReturn(new PaginatedResult([], false));

        self::assertSame(['count' => 0, 'countLabel' => '0'], (new RecordPresetService($repository))->count(RecordPreset::OpenComments, 7));
    }

    #[Test]
    public function marksACountThatReachedTheLimitAsAtLeast(): void
    {
        $repository = $this->createMock(RecordRepository::class);
        $repository->method('findAllByFilter')->willReturn(new PaginatedResult([['uid' => 1], ['uid' => 2], ['uid' => 3]], true));

        self::assertSame(['count' => 3, 'countLabel' => '3+'], (new RecordPresetService($repository))->count(RecordPreset::Todo, 7));
    }
}
