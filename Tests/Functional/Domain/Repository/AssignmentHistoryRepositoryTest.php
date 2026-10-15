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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\AssignmentHistoryRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * AssignmentHistoryRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AssignmentHistoryRepositoryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_history_assignments.csv');
    }

    #[Test]
    public function findLatestReturnsWhenAndByWhomTheCurrentAssigneeWasSet(): void
    {
        $assignment = $this->get(AssignmentHistoryRepository::class)->findLatest('pages', 1, 1);

        self::assertSame(['timestamp' => 2000, 'userUid' => 2], $assignment);
    }

    #[Test]
    public function findLatestIgnoresChangesToOtherFields(): void
    {
        $assignment = $this->get(AssignmentHistoryRepository::class)->findLatest('pages', 1, 1);

        self::assertNotSame(3000, $assignment['timestamp'] ?? null);
    }

    #[Test]
    public function findLatestOnlyConsidersTheChangeThatSetTheGivenAssignee(): void
    {
        $assignment = $this->get(AssignmentHistoryRepository::class)->findLatest('pages', 1, 2);

        self::assertSame(['timestamp' => 1000, 'userUid' => 1], $assignment);
    }

    #[Test]
    public function findLatestReturnsNullWhenNobodyAssignedThatUser(): void
    {
        self::assertNull($this->get(AssignmentHistoryRepository::class)->findLatest('pages', 1, 99));
        self::assertNull($this->get(AssignmentHistoryRepository::class)->findLatest('pages', 3, 1));
    }
}
