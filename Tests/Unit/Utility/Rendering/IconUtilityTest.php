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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Rendering;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Imaging\Icon;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;

/**
 * IconUtilityTest.
 *
 * Covers withInlineMarkup() in isolation - a plain PHP object (TYPO3\CMS\Core\Imaging\Icon)
 * built directly here rather than fetched through IconFactory, so the "does it have inline
 * markup registered" precondition is explicit instead of depending on which real icon
 * identifiers happen to have one.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class IconUtilityTest extends TestCase
{
    #[Test]
    public function withInlineMarkupSwapsInTheInlineAlternative(): void
    {
        $icon = (new Icon())
            ->setMarkup('<img src="default.svg">')
            ->setAlternativeMarkup('inline', '<svg>inline</svg>');

        $result = IconUtility::withInlineMarkup($icon);

        self::assertSame('<svg>inline</svg>', $result->getMarkup());
    }

    /**
     * IconFactory::getIcon() returns cached instances without cloning - mutating the Icon
     * handed in would leak into every other, unrelated render of the same identifier/size/state
     * for the rest of the request. withInlineMarkup() must hand back a copy instead.
     */
    #[Test]
    public function withInlineMarkupDoesNotMutateTheOriginalInstance(): void
    {
        $icon = (new Icon())
            ->setMarkup('<img src="default.svg">')
            ->setAlternativeMarkup('inline', '<svg>inline</svg>');

        $result = IconUtility::withInlineMarkup($icon);

        self::assertNotSame($icon, $result);
        self::assertSame('<img src="default.svg">', $icon->getMarkup());
    }

    /**
     * An icon whose provider never registered inline markup has an empty string there
     * ({@see Icon::getAlternativeMarkup()}) - setting that as the markup would render as an
     * empty, glyph-less icon instead of leaving the original intact.
     */
    #[Test]
    public function withInlineMarkupLeavesIconUnchangedWhenNoInlineMarkupIsRegistered(): void
    {
        $icon = (new Icon())->setMarkup('<img src="default.svg">');

        $result = IconUtility::withInlineMarkup($icon);

        self::assertSame($icon, $result);
        self::assertSame('<img src="default.svg">', $result->getMarkup());
    }
}
