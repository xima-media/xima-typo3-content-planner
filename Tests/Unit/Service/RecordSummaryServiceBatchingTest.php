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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary\CapabilitySummary;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\RecordSummaryService;

/**
 * RecordSummaryServiceBatchingTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordSummaryServiceBatchingTest extends TestCase
{
    #[Test]
    public function queriesEachRepositoryOnlyOncePerTableRegardlessOfBatchSize(): void
    {
        $records = [];
        $items = [];
        for ($uid = 1; $uid <= 25; ++$uid) {
            $records[$uid] = ['uid' => $uid, 'pid' => 1, 'title' => "Page $uid"];
            $items[] = ['table' => 'pages', 'uid' => $uid];
        }

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::once())
            ->method('findAllByUids')
            ->willReturn($records);

        $commentRepository = $this->createMock(CommentRepository::class);
        $commentRepository->expects(self::once())->method('countAllByRecords')->willReturn([]);
        $commentRepository->expects(self::once())->method('countTodosByRecords')->willReturn([]);
        $commentRepository->expects(self::never())->method('countAllByRecord');
        $commentRepository->expects(self::never())->method('countTodosByRecord');

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        // No record carries an assignee, so the user lookup must be skipped entirely
        // rather than issued once per record.
        $backendUserRepository->expects(self::never())->method('getDisplayNamesByUids');
        $backendUserRepository->expects(self::never())->method('getUsernameByUid');

        $subject = new class($recordRepository, $commentRepository, $backendUserRepository) extends RecordSummaryService {
            protected function areTodosEnabled(): bool
            {
                return true;
            }

            protected function isVisible(): bool
            {
                return true;
            }

            protected function isTableUsable(string $table): bool
            {
                return true;
            }

            /**
             * @param array<string, mixed> $record
             */
            protected function isRecordAccessible(string $table, array $record): bool
            {
                return true;
            }

            /**
             * @param array<string, mixed> $record
             */
            protected function capabilitiesFor(array $record): CapabilitySummary
            {
                return new CapabilitySummary(true, true, true);
            }
        };

        self::assertCount(25, $subject->buildForItems($items));
    }
}
