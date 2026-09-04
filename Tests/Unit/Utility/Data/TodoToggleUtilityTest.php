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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Data;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Data\TodoToggleUtility;

/**
 * TodoToggleUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class TodoToggleUtilityTest extends TestCase
{
    #[Test]
    public function checksAnUncheckedCheckboxAtTheGivenIndex(): void
    {
        $content = '<ul class="todo-list"><li><input type="checkbox">Open item</li></ul>';

        $result = TodoToggleUtility::toggle($content, 0, true);

        self::assertStringContainsString('<input type="checkbox" checked>', (string) $result);
    }

    #[Test]
    public function unchecksACheckedCheckboxAtTheGivenIndex(): void
    {
        $content = '<ul class="todo-list"><li><input type="checkbox" checked>Done item</li></ul>';

        $result = TodoToggleUtility::toggle($content, 0, false);

        self::assertStringNotContainsString('checked', (string) $result);
    }

    #[Test]
    public function onlyTogglesTheCheckboxAtTheRequestedIndex(): void
    {
        $content = '<ul class="todo-list">'
            .'<li><input type="checkbox">First</li>'
            .'<li><input type="checkbox">Second</li>'
            .'<li><input type="checkbox">Third</li>'
            .'</ul>';

        $result = (string) TodoToggleUtility::toggle($content, 1, true);

        self::assertStringContainsString('>First</li>', $result);
        self::assertStringContainsString('>Third</li>', $result);
        self::assertMatchesRegularExpression('/<input type="checkbox" checked>Second/', $result);
        self::assertDoesNotMatchRegularExpression('/<input type="checkbox" checked>First/', $result);
        self::assertDoesNotMatchRegularExpression('/<input type="checkbox" checked>Third/', $result);
    }

    #[Test]
    public function returnsNullWhenTheIndexIsBeyondTheAvailableCheckboxes(): void
    {
        $content = '<ul class="todo-list"><li><input type="checkbox">Only item</li></ul>';

        self::assertNull(TodoToggleUtility::toggle($content, 1, true));
    }

    #[Test]
    public function leavesSurroundingMarkupAndMultiByteTextUntouched(): void
    {
        $content = '<p>Before the list äöü 🚀</p><ul class="todo-list"><li><input type="checkbox">Ünicode item</li></ul><p>After</p>';

        $result = (string) TodoToggleUtility::toggle($content, 0, true);

        self::assertStringContainsString('<p>Before the list äöü 🚀</p>', $result);
        self::assertStringContainsString('Ünicode item', $result);
        self::assertStringContainsString('<p>After</p>', $result);
    }

    /**
     * CKEditor's own saved content is already canonical HTML (double-quoted attributes,
     * non-self-closing void elements) - toggle() reproduces that form exactly, so this is the
     * form every comment actually saved through the composer round-trips through unchanged.
     */
    #[Test]
    public function preservesAlreadyCanonicalMarkupExactly(): void
    {
        $content = '<p class="lead" data-x="one">Before</p><ul class="todo-list">'
            .'<li><input type="checkbox">Open item</li></ul><br>After';

        $result = TodoToggleUtility::toggle($content, 0, true);

        self::assertSame(
            '<p class="lead" data-x="one">Before</p><ul class="todo-list">'
            .'<li><input type="checkbox" checked>Open item</li></ul><br>After',
            $result,
        );
    }

    /**
     * Hand-authored non-canonical HTML (e.g. via the RTE's source-editing toolbar item) is not
     * preserved byte-for-byte: DOMDocument::saveHTML() normalizes single/unquoted attributes to
     * double quotes and drops the self-closing slash on void elements. This is a known,
     * documented trade-off (see TodoToggleUtility::toggle() docblock) - cosmetic reformatting of
     * equivalent HTML, not data loss, and it never affects CKEditor-authored content.
     */
    #[Test]
    public function normalizesNonCanonicalMarkupToEquivalentHtml(): void
    {
        $content = "<p class='lead' data-x=one>Before</p><ul class=\"todo-list\">"
            .'<li><input type="checkbox">Open item</li></ul><br/>After';

        $result = TodoToggleUtility::toggle($content, 0, true);

        self::assertSame(
            '<p class="lead" data-x="one">Before</p><ul class="todo-list">'
            .'<li><input type="checkbox" checked>Open item</li></ul><br>After',
            $result,
        );
    }
}
