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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Header;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Service\Header\StatusOptionsBuilder;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusOptionsBuilderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusOptionsBuilderTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('web_layout', ['id' => 7]);
    }

    #[Test]
    public function buildListsEveryStatusTheUserMaySetAndMarksTheCurrentOne(): void
    {
        $options = $this->get(StatusOptionsBuilder::class)->build('tt_content', 55, 2);

        self::assertSame([1, 2, 3], array_column($options['statuses'], 'uid'));
        self::assertSame([2], array_column(array_filter($options['statuses'], static fn (array $status): bool => $status['current']), 'uid'));
        self::assertSame('In Progress', $options['statuses'][1]['title']);
        self::assertStringContainsString('data[tt_content][55]', urldecode($options['statuses'][0]['url']));
        self::assertStringContainsString('id=7', urldecode(urldecode($options['statuses'][0]['url'])));
    }

    #[Test]
    public function buildOffersToRemoveTheStatusOnlyWhenThereIsOne(): void
    {
        $builder = $this->get(StatusOptionsBuilder::class);

        self::assertStringContainsString('status.reset', urldecode((string) $builder->build('tt_content', 55, 2)['resetUrl']));
        self::assertNull($builder->build('tt_content', 55, 0)['resetUrl']);
    }

    #[Test]
    public function buildOffersNothingToAUserWhoMayNotChangeTheStatus(): void
    {
        $this->setUpBackendUser(2);

        $options = $this->get(StatusOptionsBuilder::class)->build('tt_content', 55, 2);

        self::assertSame([], $options['statuses']);
        self::assertNull($options['resetUrl']);
    }
}
