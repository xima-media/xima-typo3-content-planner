<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;

final class PlannerUtilityTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: array<int, string>, 1: string}>
     */
    public static function generateTodoForComment(): array
    {
        return [
            'singleToDo' => [
                ['First task to complete'],
                '<ul class="todo-list">'
                . '<li><label class="todo-list__label">'
                . '<input type="checkbox" disabled="disabled">'
                . '<span class="todo-list__label__description">First task to complete</span>'
                . '</label></li>'
                . '</ul>',
            ],
            'multipleToDos' => [
                ['Task 1', 'Task 2'],
                '<ul class="todo-list">'
                . '<li><label class="todo-list__label">'
                . '<input type="checkbox" disabled="disabled">'
                . '<span class="todo-list__label__description">Task 1</span>'
                . '</label></li>'
                . '<li><label class="todo-list__label">'
                . '<input type="checkbox" disabled="disabled">'
                . '<span class="todo-list__label__description">Task 2</span>'
                . '</label></li>'
                . '</ul>',
            ],
            'emptyToDos' => [
                [],
                '<ul class="todo-list"></ul>',
            ],
        ];
    }

    #[DataProvider('generateTodoForComment')]
    #[Test]
    public function testGenerateTodoForComment(mixed $todos, string $expectedResult): void
    {
        self::assertSame($expectedResult, PlannerUtility::generateTodoForComment($todos));
    }
}
