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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\{AssigneeChangedEvent, CommentCreatedEvent, StatusChangeEvent};
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationPayloadFactory;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationPayloadFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationPayloadFactoryTest extends AbstractFunctionalTestCase
{
    private NotificationPayloadFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(NotificationPayloadFactory::class);
    }

    #[Test]
    public function forStatusChangeCarriesATitleSnapshotAndBothStatusTitles(): void
    {
        $event = new StatusChangeEvent('pages', 1, [], new Status(1, 'Open', '', ''), new Status(2, 'In Progress', '', ''), 1);

        self::assertSame([
            'version' => 1,
            'title' => 'Home',
            'previousStatus' => 'Open',
            'newStatus' => 'In Progress',
        ], $this->subject->forStatusChange($event));
    }

    #[Test]
    public function forStatusChangeToleratesNullStatuses(): void
    {
        $event = new StatusChangeEvent('pages', 1, [], null, null, 1);

        self::assertSame([
            'version' => 1,
            'title' => 'Home',
            'previousStatus' => null,
            'newStatus' => null,
        ], $this->subject->forStatusChange($event));
    }

    #[Test]
    public function forAssigneeChangedCarriesATitleSnapshotAndBothAssigneeUids(): void
    {
        $event = new AssigneeChangedEvent('pages', 1, 1, 2, 1);

        self::assertSame([
            'version' => 1,
            'title' => 'Home',
            'previousAssignee' => 1,
            'newAssignee' => 2,
        ], $this->subject->forAssigneeChanged($event));
    }

    #[Test]
    public function forCommentCreatedCarriesATitleSnapshotAndTheFullCommentWhenShortEnough(): void
    {
        $event = new CommentCreatedEvent('pages', 1, 1, 1);

        self::assertSame([
            'version' => 1,
            'title' => 'Home',
            'commentExcerpt' => 'Short comment',
        ], $this->subject->forCommentCreated($event));
    }

    #[Test]
    public function forCommentCreatedTruncatesALongCommentWithAnEllipsis(): void
    {
        $event = new CommentCreatedEvent('pages', 1, 2, 1);

        $payload = $this->subject->forCommentCreated($event);

        self::assertStringEndsWith('...', $payload['commentExcerpt']);
        self::assertSame(143, mb_strlen($payload['commentExcerpt']));
    }

    #[Test]
    public function forCommentCreatedReturnsAnEmptyExcerptWhenTheCommentCannotBeFound(): void
    {
        $event = new CommentCreatedEvent('pages', 1, 9999, 1);

        self::assertSame('', $this->subject->forCommentCreated($event)['commentExcerpt']);
    }
}
