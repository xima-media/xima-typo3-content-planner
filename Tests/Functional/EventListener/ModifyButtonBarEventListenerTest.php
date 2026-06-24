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
use TYPO3\CMS\Backend\Template\Components\{ButtonBar, ModifyButtonBarEvent};
use Xima\XimaTypo3ContentPlanner\EventListener\ModifyButtonBarEventListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ModifyButtonBarEventListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ModifyButtonBarEventListenerTest extends AbstractFunctionalTestCase
{
    private ModifyButtonBarEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(ModifyButtonBarEventListener::class);
    }

    #[Test]
    public function addsStatusDropdownForPageEdit(): void
    {
        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => [1 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayHasKey('right', $event->getButtons());
        self::assertNotEmpty($event->getButtons()['right']);
    }

    #[Test]
    public function addsStatusDropdownForPageViaIdParam(): void
    {
        $this->setUpBackendRequest('web_layout', ['id' => 1]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayHasKey('right', $event->getButtons());
    }

    #[Test]
    public function doesNothingWhenNoTableResolvable(): void
    {
        $this->setUpBackendRequest('web_layout', []);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayNotHasKey('right', $event->getButtons());
    }

    #[Test]
    public function doesNothingForUnregisteredTable(): void
    {
        $this->setUpBackendRequest('record_edit', ['edit' => ['sys_news' => [1 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayNotHasKey('right', $event->getButtons());
    }

    #[Test]
    public function doesNothingForUnknownPageRecord(): void
    {
        $this->setUpBackendRequest('record_edit', ['edit' => ['pages' => [999 => 'edit']]]);
        $event = $this->createEvent();

        $this->subject->__invoke($event);

        self::assertArrayNotHasKey('right', $event->getButtons());
    }

    /**
     * @param array<string, mixed> $buttons
     */
    private function createEvent(array $buttons = []): ModifyButtonBarEvent
    {
        return new ModifyButtonBarEvent($buttons, $this->get(ButtonBar::class), $GLOBALS['TYPO3_REQUEST']);
    }
}
