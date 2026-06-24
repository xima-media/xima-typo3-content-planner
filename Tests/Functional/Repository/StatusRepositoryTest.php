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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function count;

/**
 * StatusRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusRepositoryTest extends AbstractFunctionalTestCase
{
    private StatusRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->subject = $this->get(StatusRepository::class);
    }

    #[Test]
    public function findAllReturnsAllStatusesOrderedBySorting(): void
    {
        $result = $this->subject->findAll();

        self::assertCount(3, $result);
        self::assertInstanceOf(Status::class, $result[0]);
        self::assertSame('Draft', $result[0]->getTitle());
        self::assertSame('Done', $result[2]->getTitle());
    }

    #[Test]
    public function findAllReturnsCachedResultOnSecondCall(): void
    {
        $first = $this->subject->findAll();
        $second = $this->subject->findAll();

        self::assertSame(count($first), count($second));
    }

    #[Test]
    public function findByUidReturnsCorrectStatus(): void
    {
        $status = $this->subject->findByUid(2);

        self::assertInstanceOf(Status::class, $status);
        self::assertSame('In Progress', $status->getTitle());
        self::assertSame('heart', $status->getIcon());
        self::assertSame('yellow', $status->getColor());
    }

    #[Test]
    public function findByUidReturnsNullForUnknownUid(): void
    {
        self::assertNull($this->subject->findByUid(999));
    }

    #[Test]
    public function findByTitleReturnsMatchingStatus(): void
    {
        $status = $this->subject->findByTitle('Done');

        self::assertInstanceOf(Status::class, $status);
        self::assertSame(3, $status->getUid());
    }

    #[Test]
    public function findByTitleReturnsNullForUnknownTitle(): void
    {
        self::assertNull($this->subject->findByTitle('Nonexistent'));
    }
}
