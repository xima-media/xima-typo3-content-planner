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
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function sprintf;

/**
 * NotificationCenterDataProviderTest.
 *
 * The security-relevant acceptance criterion for issue #301: "Permission check on target link
 * resolution (no leaking titles of inaccessible records - fall back to generic label)". A
 * notification's title snapshot is written to its payload at dispatch time for every watcher
 * regardless of who can currently see the record (see NotificationPayloadFactory), so this has to
 * be re-checked per viewing backend user at render time - {@see self::testFallsBackForInaccessibleRecord()}
 * proves the real payload title never reaches the response for a user who no longer has access.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationCenterDataProviderTest extends AbstractFunctionalTestCase
{
    private const SECRET_TITLE = 'Confidential board minutes';

    private NotificationCenterDataProvider $subject;
    private NotificationRepository $notificationRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser(1);

        $this->subject = $this->get(NotificationCenterDataProvider::class);
        $this->notificationRepository = $this->get(NotificationRepository::class);
    }

    #[Test]
    public function fallsBackToGenericLabelAndNoLinkForInaccessibleRecord(): void
    {
        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin (same fixture
        // trick RecordControllerTest uses for the identical scenario on RecordController).
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 4);

        $items = $this->subject->getLatestForDropdown(1);

        self::assertCount(1, $items);
        self::assertStringNotContainsString(self::SECRET_TITLE, $items[0]->getTitle());
        self::assertSame(
            $GLOBALS['LANG']->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:notification.record.inaccessible'),
            $items[0]->getTitle(),
        );
        self::assertNull($items[0]->getUrl());
    }

    #[Test]
    public function resolvesTheRealTitleAndALinkForAnAccessibleRecord(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1);

        $items = $this->subject->getLatestForDropdown(1);

        self::assertCount(1, $items);
        self::assertSame(self::SECRET_TITLE, $items[0]->getTitle());
        self::assertNotNull($items[0]->getUrl());
    }

    #[Test]
    public function reasonLabelIsLocalizedFromTheStoredReasonCode(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1, reason: NotificationReason::WatchingSinceComment);

        $items = $this->subject->getLatestForDropdown(1);

        self::assertSame(
            $GLOBALS['LANG']->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:notification.reason.watching_since_comment'),
            $items[0]->getReasonLabel(),
        );
    }

    #[Test]
    public function getUnreadCountReflectsOnlyUnreadNotificationsForThatRecipient(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1);
        $this->createNotification(recipientUid: 2, table: 'pages', recordUid: 1);

        self::assertSame(1, $this->subject->getUnreadCount(1));
    }

    #[Test]
    public function changeSummaryIsPopulatedForAContentChangedNotificationWithMultipleActors(): void
    {
        $this->createNotification(
            recipientUid: 1,
            table: 'pages',
            recordUid: 1,
            eventType: NotificationEventType::ContentChanged,
            payload: ['version' => 1, 'title' => self::SECRET_TITLE, 'changeCount' => 14, 'actorUids' => [2, 3]],
        );

        $items = $this->subject->getLatestForDropdown(1);

        self::assertCount(1, $items);
        self::assertNotNull($items[0]->getChangeSummary());
        self::assertStringContainsString('14', (string) $items[0]->getChangeSummary());
    }

    #[Test]
    public function changeSummaryIsNullForNonContentChangedNotifications(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1);

        $items = $this->subject->getLatestForDropdown(1);

        self::assertNull($items[0]->getChangeSummary());
    }

    #[Test]
    public function aMentionReadsAsActorMentionedYouOnTheRecordAndIsForYou(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1, reason: NotificationReason::Mentioned, eventType: NotificationEventType::Mentioned);

        $item = $this->subject->getLatestForDropdown(1)[0];

        self::assertSame($this->label('notification.verb.mentioned.before'), $item->getVerbBefore());
        self::assertSame('', $item->getVerbAfter());
        self::assertSame($this->label('notification.tag.mentioned'), $item->getTagLabel());
        self::assertTrue($item->isTagHighlighted());
        self::assertTrue($item->isForYou());
        self::assertSame('comments', $item->getModalTab());
    }

    #[Test]
    public function anAssignmentToTheViewerIsHighlightedAndForYou(): void
    {
        $this->createNotification(
            recipientUid: 1,
            table: 'pages',
            recordUid: 1,
            eventType: NotificationEventType::Assigned,
            payload: ['version' => 1, 'title' => self::SECRET_TITLE, 'newAssignee' => 1],
        );

        $item = $this->subject->getLatestForDropdown(1)[0];

        self::assertSame($this->label('notification.verb.assigned.before'), $item->getVerbBefore());
        self::assertSame($this->label('notification.verb.assigned.afterYou'), $item->getVerbAfter());
        self::assertSame($this->label('notification.tag.assigned'), $item->getTagLabel());
        self::assertTrue($item->isTagHighlighted());
        self::assertTrue($item->isForYou());
        self::assertSame('assignee', $item->getModalTab());
    }

    #[Test]
    public function aStatusChangeNamesTheNewStatusAndOnlyCarriesTheNeutralWatchingTag(): void
    {
        $this->createNotification(
            recipientUid: 1,
            table: 'pages',
            recordUid: 1,
            eventType: NotificationEventType::StatusChanged,
            payload: ['version' => 1, 'title' => self::SECRET_TITLE, 'newStatus' => 'Completed'],
        );

        $item = $this->subject->getLatestForDropdown(1)[0];

        self::assertSame(sprintf($this->label('notification.verb.status.after'), 'Completed'), $item->getVerbAfter());
        self::assertSame($this->label('notification.tag.watching'), $item->getTagLabel());
        self::assertFalse($item->isTagHighlighted());
        self::assertFalse($item->isForYou());
    }

    #[Test]
    public function aCommentReadsAsActorCommentedOnTheRecord(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1, eventType: NotificationEventType::CommentAdded);

        $item = $this->subject->getLatestForDropdown(1)[0];

        self::assertSame($this->label('notification.verb.comment.before'), $item->getVerbBefore());
        self::assertSame('comments', $item->getModalTab());
    }

    #[Test]
    public function theEntryNamesWhereTheRecordLivesAndWhenItHappened(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 2);

        $item = $this->subject->getLatestForDropdown(1)[0];

        self::assertSame('Home', $item->getPagePath());
        self::assertSame('pages', $item->getTable());
        self::assertSame(2, $item->getRecordUid());
        self::assertSame('today', $item->getGroup());
    }

    #[Test]
    public function anOlderEntryBelongsToTheEarlierGroup(): void
    {
        $this->notificationRepository->create(new Notification(1, NotificationEventType::CommentAdded, 'pages', 1, null, NotificationReason::WatchingManually, ['version' => 1, 'title' => self::SECRET_TITLE], time() - 2 * 86400));

        self::assertSame('earlier', $this->subject->getLatestForDropdown(1)[0]->getGroup());
    }

    #[Test]
    public function theLinkOpensTheRecordWithTheModalOnTheMatchingTab(): void
    {
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 1, eventType: NotificationEventType::Assigned, payload: ['version' => 1, 'title' => self::SECRET_TITLE, 'newAssignee' => 1]);

        $url = (string) $this->subject->getLatestForDropdown(1)[0]->getUrl();

        self::assertStringContainsString('tab=assignee', $url);
    }

    #[Test]
    public function theActorReadsAsTheirNameWithoutTheUsernameSoTheSentenceStaysShort(): void
    {
        $this->notificationRepository->create(new Notification(1, NotificationEventType::CommentAdded, 'pages', 1, 2, NotificationReason::WatchingManually, ['version' => 1, 'title' => self::SECRET_TITLE], time()));

        self::assertSame('Editor User', $this->subject->getLatestForDropdown(1)[0]->getActorLabel());
    }

    private function label(string $key): string
    {
        return $GLOBALS['LANG']->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:'.$key);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function createNotification(
        int $recipientUid,
        string $table,
        int $recordUid,
        NotificationReason $reason = NotificationReason::WatchingManually,
        NotificationEventType $eventType = NotificationEventType::StatusChanged,
        ?array $payload = null,
    ): void {
        $this->notificationRepository->create(new Notification(
            $recipientUid,
            $eventType,
            $table,
            $recordUid,
            null,
            $reason,
            $payload ?? ['version' => 1, 'title' => self::SECRET_TITLE],
            time(),
        ));
    }
}
