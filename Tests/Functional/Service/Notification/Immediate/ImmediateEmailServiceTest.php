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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification\Immediate;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, ImmediateEmailQueueRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\{DigestGroupBuilder, DigestMailFactory};
use Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\Command\Fixtures\DigestMailerSpy;

/**
 * ImmediateEmailServiceTest.
 *
 * Functional coverage for issue #306's acceptance criteria: the first event for a `(recipient,
 * record)` pair is sent right away, any further event within the 15 minute throttle window is
 * batched instead of triggering its own mail, and once the window has passed the next event
 * flushes everything queued into a single mail - reusing the exact same `NotificationDigest`
 * template/grouping pipeline as the daily digest (issue #302).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ImmediateEmailServiceTest extends AbstractFunctionalTestCase
{
    private const RECIPIENT = 2;

    private ImmediateEmailService $subject;
    private ImmediateEmailQueueRepository $queueRepository;
    private DigestMailerSpy $mailerSpy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queueRepository = $this->get(ImmediateEmailQueueRepository::class);
        $this->mailerSpy = new DigestMailerSpy();

        $this->subject = new ImmediateEmailService(
            $this->queueRepository,
            new DigestGroupBuilder(),
            new DigestMailFactory($this->get(RecordRepository::class)),
            $this->mailerSpy,
            $this->get(BackendUserRepository::class),
            $this->get(LanguageServiceFactory::class),
        );
    }

    #[Test]
    public function theFirstEventOnARecordIsSentRightAway(): void
    {
        $this->subject->handle($this->statusChange(crdate: 1000, previous: null, new: 'Draft'), $this->recipient());

        self::assertCount(1, $this->mailerSpy->sentMessages);
        self::assertStringContainsString('Status: Draft', (string) $this->mailerSpy->sentMessages[0]->getHtmlBody());
    }

    #[Test]
    public function aSecondEventWithinTheThrottleWindowDoesNotSendItsOwnMail(): void
    {
        $this->subject->handle($this->statusChange(crdate: 1000, previous: null, new: 'Draft'), $this->recipient());
        $this->subject->handle($this->statusChange(crdate: 1001, previous: 'Draft', new: 'Review'), $this->recipient());

        self::assertCount(1, $this->mailerSpy->sentMessages);
        self::assertCount(1, $this->queueRepository->findPending(self::RECIPIENT, 'pages', 1));
    }

    #[Test]
    public function everythingBatchedInsideTheWindowIsFlushedTogetherOnceItExpires(): void
    {
        $this->subject->handle($this->statusChange(crdate: 1000, previous: null, new: 'Draft'), $this->recipient());
        $this->subject->handle($this->statusChange(crdate: 1001, previous: 'Draft', new: 'Review'), $this->recipient());
        $this->expireTheThrottleWindow();

        $this->subject->handle($this->statusChange(crdate: 1002, previous: 'Review', new: 'Approved'), $this->recipient());

        self::assertCount(2, $this->mailerSpy->sentMessages);
        $secondMail = $this->mailerSpy->sentMessages[1];
        // The queued "Draft -> Review" event and the new "Review -> Approved" event are batched
        // into one deduped transition chain, exactly like the daily digest would (issue #302).
        self::assertStringContainsString('Status: Draft → Review → Approved', (string) $secondMail->getHtmlBody());
        self::assertCount(0, $this->queueRepository->findPending(self::RECIPIENT, 'pages', 1));
    }

    #[Test]
    public function theMailReusesTheDigestSubjectAndBodyTemplate(): void
    {
        $this->subject->handle($this->statusChange(crdate: 1000, previous: null, new: 'Draft'), $this->recipient());

        $mail = $this->mailerSpy->sentMessages[0];
        self::assertSame('Content Planner: 1 update(s) to review', $mail->getSubject());
        self::assertStringContainsString('Status: Draft', (string) $mail->getTextBody());
    }

    #[Test]
    public function separateRecordsAreThrottledIndependently(): void
    {
        $this->subject->handle($this->statusChange(crdate: 1000, previous: null, new: 'Draft', recordUid: 1), $this->recipient());
        $this->subject->handle($this->statusChange(crdate: 1000, previous: null, new: 'Draft', recordUid: 2), $this->recipient());

        self::assertCount(2, $this->mailerSpy->sentMessages);
    }

    private function statusChange(int $crdate, ?string $previous, string $new, int $recordUid = 1): Notification
    {
        return new Notification(
            self::RECIPIENT,
            NotificationEventType::StatusChanged,
            'pages',
            $recordUid,
            1,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home', 'previousStatus' => $previous, 'newStatus' => $new],
            $crdate,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function recipient(): array
    {
        return [
            'uid' => self::RECIPIENT,
            'username' => 'editor',
            'realName' => 'Editor User',
            'email' => 'editor@example.com',
            'lang' => 'default',
            Configuration::FIELD_USER_DIGEST => 1,
            Configuration::FIELD_USER_IMMEDIATE_EMAIL => 1,
        ];
    }

    /**
     * Simulates the 15 minute throttle window having passed without a real sleep(): the window
     * is anchored on the stored `sent_at` value, so moving it into the past has the exact same
     * effect as time actually passing. Deliberately scoped to already-sent rows only - the
     * still-queued (unsent) row must stay untouched so it is genuinely included the next time
     * `findPending()` collects everything to flush.
     */
    private function expireTheThrottleWindow(): void
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);
        $queryBuilder
            ->update(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->set('sent_at', time() - 1000)
            ->where($queryBuilder->expr()->isNotNull('sent_at'))
            ->executeStatement();
    }
}
