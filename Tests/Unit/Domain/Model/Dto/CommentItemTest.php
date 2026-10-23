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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

/**
 * CommentItemTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentItemTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plainTextProvider(): iterable
    {
        yield 'table cells stay separated' => ['<table><tr><td>Approved</td><td>Rejected</td></tr></table>', 'Approved Rejected'];
        yield 'paragraphs stay separated' => ['<p>First</p><p>Second</p>', 'First Second'];
        yield 'line breaks become spaces' => ['One<br>Two<br/>Three', 'One Two Three'];
        yield 'list items stay separated' => ['<ul><li>A</li><li>B</li></ul>', 'A B'];
        yield 'inline tags do not add spaces' => ['<p>Hel<strong>lo</strong></p>', 'Hello'];
        yield 'entities are decoded' => ['<p>Intro&nbsp;text &amp; more</p>', 'Intro text & more'];
        yield 'encoded tags stay text' => ['&lt;b&gt;escaped&lt;/b&gt;', '<b>escaped</b>'];
    }

    #[Test]
    #[DataProvider('plainTextProvider')]
    public function getPlainTextReturnsReadableText(string $content, string $expected): void
    {
        $item = CommentItem::create(['content' => $content]);

        self::assertSame($expected, $item->getPlainText());
    }
}
