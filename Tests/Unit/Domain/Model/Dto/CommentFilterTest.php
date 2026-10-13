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

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentFilter;

/**
 * CommentFilterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentFilterTest extends TestCase
{
    /**
     * @param array<string, mixed> $params
     */
    #[Test]
    #[DataProvider('queryParams')]
    public function derivesTheRepositoryFlagsFromTheShowChoice(array $params, string $show, bool $resolved, bool $todo): void
    {
        $filter = CommentFilter::fromQueryParams($params);

        self::assertSame($show, $filter->show);
        self::assertSame($resolved, $filter->showResolved);
        self::assertSame($todo, $filter->showTodo);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, bool, bool}>
     */
    public static function queryParams(): array
    {
        return [
            'nothing asks for open comments' => [[], 'open', false, false],
            'show todo' => [['show' => 'todo'], 'todo', false, true],
            'show all' => [['show' => 'all'], 'all', true, false],
            'show resolved' => [['show' => 'resolved'], 'resolved', true, false],
            'unknown choice falls back to open' => [['show' => 'bogus'], 'open', false, false],
            'legacy resolved flag' => [['showResolvedComments' => '1'], 'all', true, false],
            'legacy todo flag' => [['showTodoComments' => '1'], 'todo', false, true],
            'both legacy flags keep the todo choice' => [['showResolvedComments' => '1', 'showTodoComments' => '1'], 'todo', true, true],
        ];
    }

    #[Test]
    public function onlyTheResolvedChoiceNarrowsToResolvedComments(): void
    {
        self::assertTrue(CommentFilter::fromQueryParams(['show' => 'resolved'])->onlyResolved);
        self::assertFalse(CommentFilter::fromQueryParams(['show' => 'all'])->onlyResolved);
    }

    #[Test]
    public function theLoadAllFlagIsOptIn(): void
    {
        self::assertFalse(CommentFilter::fromQueryParams([])->loadAll);
        self::assertTrue(CommentFilter::fromQueryParams(['all' => '1'])->loadAll);
    }
}
