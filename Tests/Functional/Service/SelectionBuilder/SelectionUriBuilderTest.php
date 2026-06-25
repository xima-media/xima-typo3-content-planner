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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\SelectionBuilder;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\UriInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\SelectionUriBuilder;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * SelectionUriBuilderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class SelectionUriBuilderTest extends AbstractFunctionalTestCase
{
    private SelectionUriBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->subject = $this->get(SelectionUriBuilder::class);
    }

    #[Test]
    public function buildUriForStatusChangeWithRecordEditRouteEncodesStatusData(): void
    {
        $this->setUpBackendRequest('record_edit', ['id' => 1]);
        $status = $this->makeStatus();

        $uri = $this->subject->buildUriForStatusChange('pages', 1, $status);

        self::assertInstanceOf(UriInterface::class, $uri);
        $decoded = urldecode((string) $uri);
        self::assertStringContainsString('data[pages][1][tx_ximatypo3contentplanner_status]=1', $decoded);
        self::assertStringContainsString('status.changed', $decoded);
    }

    #[Test]
    public function buildUriForStatusChangeWithRecordListRouteResetsStatus(): void
    {
        $this->setUpBackendRequest('web_list', ['id' => 5]);
        $uri = $this->subject->buildUriForStatusChange('pages', 5, null);

        $decoded = urldecode((string) $uri);
        self::assertStringContainsString('status.reset', $decoded);
    }

    #[Test]
    public function buildUriForStatusChangeWithArrayUidEncodesAllIds(): void
    {
        $this->setUpBackendRequest('record_edit', ['id' => 1]);
        $status = $this->makeStatus();

        $uri = $this->subject->buildUriForStatusChange('pages', [3, 4], $status);

        $decoded = urldecode((string) $uri);
        self::assertStringContainsString('data[pages][3]', $decoded);
        self::assertStringContainsString('data[pages][4]', $decoded);
    }

    #[Test]
    public function buildUriForStatusChangeWithUnknownRouteFallsBack(): void
    {
        $this->setUpBackendRequest('web_layout', ['id' => 1]);
        $uri = $this->subject->buildUriForStatusChange('pages', 1, null);

        self::assertInstanceOf(UriInterface::class, $uri);
        self::assertStringContainsString('status.reset', urldecode((string) $uri));
    }

    private function makeStatus(): Status
    {
        $status = new Status();
        $status->setTitle('Draft');
        $status->setIcon('flag');
        $status->setColor('blue');
        $status->_setProperty('uid', 1);

        return $status;
    }
}
