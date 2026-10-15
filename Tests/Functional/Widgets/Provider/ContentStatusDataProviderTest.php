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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Widgets\Provider;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentStatusDataProvider;

/**
 * ContentStatusDataProviderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentStatusDataProviderTest extends AbstractFunctionalTestCase
{
    private ContentStatusDataProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ContentStatusDataProvider();
    }

    #[Test]
    public function getItemsReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getItems());
    }
}
