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
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListTableActionsEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\ModifyRecordListTableActionsListener;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ModifyRecordListTableActionsListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ModifyRecordListTableActionsListenerTest extends AbstractFunctionalTestCase
{
    private ModifyRecordListTableActionsListener $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('web_list', ['id' => 1]);
        $this->subject = $this->get(ModifyRecordListTableActionsListener::class);
    }

    #[Test]
    public function addsStatusActionForRegisteredTable(): void
    {
        $event = $this->createEvent('pages');

        $this->subject->__invoke($event);

        self::assertTrue($event->hasAction('Status'));
        self::assertStringContainsString('dropdown-menu', (string) $event->getAction('Status'));
    }

    #[Test]
    public function addsNoActionForUnregisteredTable(): void
    {
        $event = $this->createEvent('sys_news');

        $this->subject->__invoke($event);

        self::assertFalse($event->hasAction('Status'));
    }

    #[Test]
    public function skipsWhenStatusActionAlreadyPresent(): void
    {
        $recordList = $this->get(DatabaseRecordList::class);
        $recordList->id = 1;
        $recordList->table = '';
        $event = new ModifyRecordListTableActionsEvent(['Status' => 'existing'], 'pages', [1], $recordList);

        $this->subject->__invoke($event);

        self::assertSame('existing', $event->getAction('Status'));
    }

    /**
     * @param int[] $recordIds
     */
    private function createEvent(string $table, array $recordIds = [1]): ModifyRecordListTableActionsEvent
    {
        $recordList = $this->get(DatabaseRecordList::class);
        $recordList->id = 1;
        $recordList->table = '';

        return new ModifyRecordListTableActionsEvent([], $table, $recordIds, $recordList);
    }
}
