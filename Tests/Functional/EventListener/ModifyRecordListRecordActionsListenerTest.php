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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Backend\Template\Components\ComponentGroup;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\RecordFactory;
use Xima\XimaTypo3ContentPlanner\EventListener\ModifyRecordListRecordActionsListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;

/**
 * ModifyRecordListRecordActionsListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ModifyRecordListRecordActionsListenerTest extends AbstractFunctionalTestCase
{
    private ModifyRecordListRecordActionsListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        if (!VersionUtility::is14OrHigher()) {
            self::markTestSkipped('ModifyRecordListRecordActionsEvent relies on ComponentGroup, available since TYPO3 v14.');
        }

        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('web_list', ['id' => 1]);
        $this->subject = $this->get(ModifyRecordListRecordActionsListener::class);
    }

    #[Test]
    public function addsStatusActionForRegisteredTable(): void
    {
        $event = $this->createEventForPage(1);

        $this->subject->__invoke($event);

        self::assertTrue($event->hasAction('Status'));
    }

    #[Test]
    public function addsStatusActionForPageWithoutOwnStatus(): void
    {
        $event = $this->createEventForPage(2);

        $this->subject->__invoke($event);

        self::assertTrue($event->hasAction('Status'));
    }

    #[Test]
    public function skipsWhenStatusActionAlreadyPresent(): void
    {
        $row = BackendUtility::getRecord('pages', 1);
        self::assertIsArray($row);
        $resolvedRecord = $this->get(RecordFactory::class)->createFromDatabaseRow('pages', $row);

        $primary = new ComponentGroup('primary');
        $existing = $this->createMock(\TYPO3\CMS\Backend\Template\Components\ComponentInterface::class);
        $primary->add('Status', $existing);

        $event = new ModifyRecordListRecordActionsEvent(
            $primary,
            new ComponentGroup('secondary'),
            $resolvedRecord,
            $this->createMock(DatabaseRecordList::class),
            $this->createMock(ServerRequestInterface::class),
        );

        $this->subject->__invoke($event);

        self::assertSame($existing, $event->getAction('Status'));
    }

    private function createEventForPage(int $uid): ModifyRecordListRecordActionsEvent
    {
        $row = BackendUtility::getRecord('pages', $uid);
        self::assertIsArray($row);
        $resolvedRecord = $this->get(RecordFactory::class)->createFromDatabaseRow('pages', $row);

        return new ModifyRecordListRecordActionsEvent(
            new ComponentGroup('primary'),
            new ComponentGroup('secondary'),
            $resolvedRecord,
            $this->createMock(DatabaseRecordList::class),
            $this->createMock(ServerRequestInterface::class),
        );
    }
}
