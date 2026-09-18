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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Header\Buttons;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Service\Header\Buttons\RawMarkupButton;

/**
 * RawMarkupButtonTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RawMarkupButtonTest extends TestCase
{
    #[Test]
    public function renderReturnsTheGivenMarkupVerbatim(): void
    {
        $button = new RawMarkupButton('<button>watch</button>');

        self::assertSame('<button>watch</button>', $button->render());
    }

    #[Test]
    public function toStringMatchesRender(): void
    {
        $button = new RawMarkupButton('<button>watch</button>');

        self::assertSame($button->render(), (string) $button);
    }

    #[Test]
    public function isValidIsTrueForNonEmptyMarkup(): void
    {
        self::assertTrue((new RawMarkupButton('<button>watch</button>'))->isValid());
    }

    #[Test]
    public function isValidIsFalseForEmptyOrWhitespaceOnlyMarkup(): void
    {
        self::assertFalse((new RawMarkupButton(''))->isValid());
        self::assertFalse((new RawMarkupButton('   '))->isValid());
    }

    #[Test]
    public function getTypeReturnsOwnClassName(): void
    {
        self::assertSame(RawMarkupButton::class, (new RawMarkupButton('x'))->getType());
    }
}
