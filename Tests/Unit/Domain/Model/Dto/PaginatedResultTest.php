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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Model\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;

/**
 * PaginatedResultTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class PaginatedResultTest extends TestCase
{
    #[Test]
    public function constructorHydratesItemsAndHasMore(): void
    {
        $result = new PaginatedResult(['a', 'b'], true);

        self::assertSame(['a', 'b'], $result->items);
        self::assertTrue($result->hasMore);
    }

    #[Test]
    public function defaultConstructionAcceptsEmptyItemsAndFalseHasMore(): void
    {
        $result = new PaginatedResult([], false);

        self::assertSame([], $result->items);
        self::assertFalse($result->hasMore);
    }

    #[Test]
    public function paginatedResultIsReadonly(): void
    {
        $reflection = new ReflectionClass(PaginatedResult::class);

        self::assertTrue($reflection->isReadOnly());
    }
}
