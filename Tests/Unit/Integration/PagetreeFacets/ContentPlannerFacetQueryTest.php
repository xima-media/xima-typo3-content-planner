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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Integration\PagetreeFacets;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacetQuery;

/**
 * ContentPlannerFacetQueryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentPlannerFacetQueryTest extends TestCase
{
    private ContentPlannerFacetQuery $subject;

    protected function setUp(): void
    {
        $this->subject = new ContentPlannerFacetQuery($this->createMock(ConnectionPool::class));
    }

    public function testFilterAllowedStatusUidsIntersectsWhenAllowedListIsNotEmpty(): void
    {
        self::assertSame([2], $this->subject->filterAllowedStatusUids([2, 3], [2]));
    }

    public function testFilterAllowedStatusUidsReturnsAllWhenAllowedListIsEmpty(): void
    {
        self::assertSame([2, 3], $this->subject->filterAllowedStatusUids([2, 3], []));
    }

    public function testFilterAllowedStatusUidsReturnsEmptyWhenNoneMatch(): void
    {
        self::assertSame([], $this->subject->filterAllowedStatusUids([2, 3], [9]));
    }
}
