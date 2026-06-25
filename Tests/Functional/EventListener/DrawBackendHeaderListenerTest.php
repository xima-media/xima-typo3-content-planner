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
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use Xima\XimaTypo3ContentPlanner\EventListener\DrawBackendHeaderListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * DrawBackendHeaderListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DrawBackendHeaderListenerTest extends AbstractFunctionalTestCase
{
    private DrawBackendHeaderListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(DrawBackendHeaderListener::class);
    }

    #[Test]
    public function addsHeaderContentForPageWithStatus(): void
    {
        $event = $this->createEvent(1);

        $this->subject->__invoke($event);

        self::assertNotSame('', $event->getHeaderContent());
    }

    #[Test]
    public function addsNoHeaderContentForPageWithoutStatus(): void
    {
        $event = $this->createEvent(2);

        $this->subject->__invoke($event);

        self::assertSame('', $event->getHeaderContent());
    }

    #[Test]
    public function addsNoHeaderContentForUnknownPage(): void
    {
        $event = $this->createEvent(999);

        $this->subject->__invoke($event);

        self::assertSame('', $event->getHeaderContent());
    }

    private function createEvent(int $pageId): ModifyPageLayoutContentEvent
    {
        $request = $this->setUpBackendRequest('web_layout', ['id' => $pageId])
            ->withAttribute('route', new Route('/dummy', ['packageName' => 'typo3/cms-backend']));

        return new ModifyPageLayoutContentEvent(
            $request,
            $this->get(ModuleTemplateFactory::class)->create($request),
        );
    }
}
