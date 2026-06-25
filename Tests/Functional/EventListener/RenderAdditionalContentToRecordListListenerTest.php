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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\EventListener\RenderAdditionalContentToRecordListListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RenderAdditionalContentToRecordListListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RenderAdditionalContentToRecordListListenerTest extends AbstractFunctionalTestCase
{
    private RenderAdditionalContentToRecordListListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_RECORD_LIST_STATUS_INFO] = 1;
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(RenderAdditionalContentToRecordListListener::class);
    }

    #[Test]
    public function addsStatusCssForPagesWithStatus(): void
    {
        $event = $this->createEvent(['id' => 1, 'table' => 'pages']);

        $this->subject->__invoke($event);

        self::assertStringContainsString('background-color', $event->getAdditionalContentAbove());
        self::assertStringContainsString('data-table="pages"', $event->getAdditionalContentAbove());
    }

    #[Test]
    public function addsNoCssWhenNoIdProvided(): void
    {
        $event = $this->createEvent(['table' => 'pages']);

        $this->subject->__invoke($event);

        self::assertSame('', $event->getAdditionalContentAbove());
    }

    #[Test]
    public function addsNoCssForUnregisteredTable(): void
    {
        $event = $this->createEvent(['id' => 1, 'table' => 'sys_news']);

        $this->subject->__invoke($event);

        self::assertSame('', $event->getAdditionalContentAbove());
    }

    #[Test]
    public function addsNoCssForPageWithoutStatusChildren(): void
    {
        $event = $this->createEvent(['id' => 999, 'table' => 'pages']);

        $this->subject->__invoke($event);

        self::assertSame('', $event->getAdditionalContentAbove());
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createEvent(array $queryParams): RenderAdditionalContentToRecordListEvent
    {
        $request = $this->setUpBackendRequest('web_list', $queryParams);

        return new RenderAdditionalContentToRecordListEvent($request);
    }
}
