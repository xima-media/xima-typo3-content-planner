<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;

/**
 * PlannerUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class PlannerUtilityTest extends TestCase
{
    public function testGenerateTodoForCommentWithSingleTodo(): void
    {
        $todos = ['First task to complete'];

        $result = PlannerUtility::generateTodoForComment($todos);

        $expected = '<ul class="todo-list">'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">First task to complete</span>'
            .'</label></li>'
            .'</ul>';

        self::assertSame($expected, $result);
    }

    public function testGenerateTodoForCommentWithMultipleTodos(): void
    {
        $todos = ['First task', 'Second task', 'Third task'];

        $result = PlannerUtility::generateTodoForComment($todos);

        $expected = '<ul class="todo-list">'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">First task</span>'
            .'</label></li>'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">Second task</span>'
            .'</label></li>'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">Third task</span>'
            .'</label></li>'
            .'</ul>';

        self::assertSame($expected, $result);
    }

    public function testGenerateTodoForCommentWithEmptyArray(): void
    {
        $todos = [];

        $result = PlannerUtility::generateTodoForComment($todos);

        $expected = '<ul class="todo-list"></ul>';

        self::assertSame($expected, $result);
    }

    public function testGenerateTodoForCommentWithHtmlEntities(): void
    {
        $todos = ['Task with <script>alert("xss")</script>', 'Task with "quotes" & ampersand'];

        $result = PlannerUtility::generateTodoForComment($todos);

        $expected = '<ul class="todo-list">'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">Task with &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;</span>'
            .'</label></li>'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">Task with &quot;quotes&quot; &amp; ampersand</span>'
            .'</label></li>'
            .'</ul>';

        self::assertSame($expected, $result);
    }

    public function testGenerateTodoForCommentWithUnicodeCharacters(): void
    {
        $todos = ['Task with émojis 🚀', 'Ünicode ñames'];

        $result = PlannerUtility::generateTodoForComment($todos);

        $expected = '<ul class="todo-list">'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">Task with émojis 🚀</span>'
            .'</label></li>'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">Ünicode ñames</span>'
            .'</label></li>'
            .'</ul>';

        self::assertSame($expected, $result);
    }

    public function testGenerateTodoForCommentWithVeryLongText(): void
    {
        $longText = str_repeat('Very long task description ', 50);
        $todos = [$longText];

        $result = PlannerUtility::generateTodoForComment($todos);

        self::assertStringContainsString($longText, $result);
        self::assertStringContainsString('<ul class="todo-list">', $result);
        self::assertStringContainsString('</ul>', $result);
    }

    public function testGenerateTodoForCommentWithNumericTodos(): void
    {
        $todos = ['123', '456.789'];

        $result = PlannerUtility::generateTodoForComment($todos);

        $expected = '<ul class="todo-list">'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">123</span>'
            .'</label></li>'
            .'<li><label class="todo-list__label">'
            .'<input type="checkbox" disabled="disabled">'
            .'<span class="todo-list__label__description">456.789</span>'
            .'</label></li>'
            .'</ul>';

        self::assertSame($expected, $result);
    }
}
