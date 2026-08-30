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
use TYPO3\CMS\Core\Cache\CacheManager;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function sprintf;

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

        $identify = static fn (array $statuses): array => array_map(
            static fn (Status $status): string => $status->getUid().':'.$status->getTitle(),
            $statuses,
        );

        self::assertSame($identify($first), $identify($second));
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
    public function findByUidReturnsSameInstanceOnSecondCallViaRuntimeCache(): void
    {
        $first = $this->subject->findByUid(2);
        $second = $this->subject->findByUid(2);

        // Without runtime memoization the second call would be served from the (serializing)
        // cache backend and thus return a different object instance.
        self::assertSame($first, $second);
    }

    #[Test]
    public function findByUidMemoizesUnknownUid(): void
    {
        self::assertNull($this->subject->findByUid(999));
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

    #[Test]
    public function findAllHydratesIsDefaultAsFalseWhenFlagIsNotSet(): void
    {
        self::assertFalse($this->subject->findAll()[0]->isDefault());
    }

    #[Test]
    public function findDefaultReturnsNullWhenNoStatusIsMarkedAsDefault(): void
    {
        self::assertNull($this->subject->findDefault());
    }

    #[Test]
    public function findDefaultReturnsTheStatusMarkedAsDefault(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_with_default.csv');
        $subject = $this->get(StatusRepository::class);

        $default = $subject->findDefault();

        self::assertInstanceOf(Status::class, $default);
        self::assertSame(12, $default->getUid());
        self::assertTrue($default->isDefault());
    }

    #[Test]
    public function statusCachedUnderThePreviousIdentifierIsIgnored(): void
    {
        // The cache stores serialized Status objects, and unserialize() does not run the
        // constructor. An entry written before Status gained is_default therefore came back
        // with that property uninitialized, and the first isDefault() call fatalled until
        // someone flushed the cache by hand. The identifier now carries a shape version, so
        // entries written under the old one are simply missed.
        $cache = $this->get(CacheManager::class)->getCache(Configuration::CACHE_IDENTIFIER.'_cache');
        $cache->set(
            sprintf('%s--status--1', Configuration::CACHE_IDENTIFIER),
            new Status(1, 'Stale', 'flag', 'gray'),
        );

        $result = $this->get(StatusRepository::class)->findByUid(1);

        self::assertInstanceOf(Status::class, $result);
        self::assertSame('Draft', $result->getTitle(), 'Expected a freshly loaded status, not the entry under the old identifier.');
    }
}
