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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Rendering;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\CountLabelUtility;

/**
 * CountLabelUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CountLabelUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
    }

    #[Test]
    public function commentsNamesTheButtonWithoutACountWhenThereAreNone(): void
    {
        self::assertSame('Comments', CountLabelUtility::comments(0));
    }

    #[Test]
    public function commentsUsesTheSingularForOne(): void
    {
        self::assertSame('1 comment', CountLabelUtility::comments(1));
    }

    #[Test]
    public function commentsUsesThePluralForMoreThanOne(): void
    {
        self::assertSame('3 comments', CountLabelUtility::comments(3));
    }

    #[Test]
    public function todosNamesProgressAsText(): void
    {
        self::assertSame('0 of 2 to-dos done', CountLabelUtility::todos(0, 2));
    }
}
