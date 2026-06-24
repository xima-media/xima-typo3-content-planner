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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Header;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Service\Header\HeaderMode;

/**
 * HeaderModeTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class HeaderModeTest extends TestCase
{
    #[Test]
    public function casesHaveExpectedValues(): void
    {
        self::assertSame('edit', HeaderMode::EDIT->value);
        self::assertSame('web_layout', HeaderMode::WEB_LAYOUT->value);
        self::assertSame('web_list', HeaderMode::WEB_LIST->value);
        self::assertSame('file_list', HeaderMode::FILE_LIST->value);
    }

    #[Test]
    public function fromReturnsMatchingCase(): void
    {
        self::assertSame(HeaderMode::EDIT, HeaderMode::from('edit'));
        self::assertSame(HeaderMode::WEB_LAYOUT, HeaderMode::from('web_layout'));
        self::assertSame(HeaderMode::WEB_LIST, HeaderMode::from('web_list'));
        self::assertSame(HeaderMode::FILE_LIST, HeaderMode::from('file_list'));
    }

    #[Test]
    public function tryFromReturnsNullForUnknownValue(): void
    {
        self::assertNull(HeaderMode::tryFrom('unknown'));
    }

    #[Test]
    public function hasExactlyFourCases(): void
    {
        self::assertCount(4, HeaderMode::cases());
    }
}
