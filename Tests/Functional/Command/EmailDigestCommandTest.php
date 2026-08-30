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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Xima\XimaTypo3ContentPlanner\Command\EmailDigestCommand;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, NotificationRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\{DigestGroupBuilder, DigestMailFactory, DigestService};
use Xima\XimaTypo3ContentPlanner\Service\Notification\RecipientAccessChecker;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\Command\Fixtures\DigestMailerSpy;

/**
 * EmailDigestCommandTest.
 *
 * Functional coverage for issue #302's acceptance criteria: one mail per recipient max per run,
 * no mail when nothing to digest, dedup of same-record notifications into one line, opt-out and
 * missing-email handling, and that `digested_at` is only ever set for the notifications a run
 * actually rendered into a sent mail.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class EmailDigestCommandTest extends AbstractFunctionalTestCase
{
    private const RECIPIENT_EDITOR = 2;
    private const RECIPIENT_NO_EMAIL = 3;
    private const RECIPIENT_OPTED_OUT = 4;
    private const RECIPIENT_IMMEDIATE = 5;

    private CommandTester $tester;
    private NotificationRepository $notificationRepository;
    private DigestMailerSpy $mailerSpy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_users_digest.csv');
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_DIGEST_EMAIL, '1');

        $this->notificationRepository = $this->get(NotificationRepository::class);
        $this->mailerSpy = new DigestMailerSpy();

        $digestService = new DigestService(
            $this->notificationRepository,
            $this->get(BackendUserRepository::class),
            new DigestGroupBuilder(),
            new DigestMailFactory($this->get(RecordRepository::class)),
            $this->mailerSpy,
            $this->get(LanguageServiceFactory::class),
            $this->get(RecordRepository::class),
            $this->get(RecipientAccessChecker::class),
        );

        $command = new EmailDigestCommand($this->notificationRepository, $digestService);
        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function fiveStatusChangesOnOneRecordProduceOneMailWithADedupedTransitionLine(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft');
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1001, previous: 'Draft', new: 'Review');
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1002, previous: 'Review', new: 'Review');
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1003, previous: 'Review', new: 'Review');
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1004, previous: 'Review', new: 'Approved');

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $this->mailerSpy->sentMessages);

        $mail = $this->mailerSpy->sentMessages[0];
        self::assertSame(['editor@example.com'], array_map(static fn ($a): string => $a->getAddress(), $mail->getTo()));
        self::assertStringContainsString('Status: Draft → Review → Approved', (string) $mail->getHtmlBody());
        self::assertStringContainsString('Status: Draft → Review → Approved', (string) $mail->getTextBody());

        // Digesting is independent of the backend toolbar's read-state (see #302/#301): it never
        // touches `read_at`, only `digested_at`.
        self::assertSame(5, $this->notificationRepository->countUnreadByRecipient(self::RECIPIENT_EDITOR));
        self::assertSame([], $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_EDITOR));
    }

    #[Test]
    public function contentChangeNotificationsRenderAsACollapsedEntryWithTheChangeCount(): void
    {
        // Two aggregated rows (e.g. two separate days) collapse further at digest time into one line.
        $this->createContentChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, changeCount: 9, actorUids: [10, 11]);
        $this->createContentChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 2000, changeCount: 5, actorUids: [11]);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $this->mailerSpy->sentMessages);
        $mail = $this->mailerSpy->sentMessages[0];
        self::assertStringContainsString('Content edited by 2 users, 14 change(s)', (string) $mail->getHtmlBody());
        self::assertStringContainsString('Content edited by 2 users, 14 change(s)', (string) $mail->getTextBody());
    }

    #[Test]
    public function oneMailMaximumPerRecipientEvenAcrossMultipleRecordsAndEventTypes(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft', recordUid: 1);
        $this->createAssigneeChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1001, previous: null, new: 2, recordUid: 2);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $this->mailerSpy->sentMessages);
    }

    #[Test]
    public function noMailIsSentWhenThereIsNothingToDigest(): void
    {
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(0, $this->mailerSpy->sentMessages);
        self::assertStringContainsString('Nothing to digest.', $this->tester->getDisplay());
    }

    #[Test]
    public function optedOutRecipientReceivesNoMailAndItsNotificationsStayPending(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_OPTED_OUT, crdate: 1000, previous: null, new: 'Draft');

        $this->tester->execute([]);

        self::assertCount(0, $this->mailerSpy->sentMessages);
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_OPTED_OUT));
    }

    #[Test]
    public function recipientOnTheImmediateChannelReceivesNoDigestMailAndItsNotificationsStayPending(): void
    {
        // Issue #306: a recipient using the immediate channel already got a separate mail per
        // event, so the daily digest must not notify them again for the same notifications.
        $this->createStatusChange(recipientUid: self::RECIPIENT_IMMEDIATE, crdate: 1000, previous: null, new: 'Draft');

        $this->tester->execute([]);

        self::assertCount(0, $this->mailerSpy->sentMessages);
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_IMMEDIATE));
    }

    #[Test]
    public function recipientWithoutAnEmailAddressIsSkippedAndItsNotificationsStayPending(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_NO_EMAIL, crdate: 1000, previous: null, new: 'Draft');

        $this->tester->execute([]);

        self::assertCount(0, $this->mailerSpy->sentMessages);
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_NO_EMAIL));
    }

    #[Test]
    public function dryRunSendsNoMailAndMarksNothingDigested(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft');

        $exitCode = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(0, $this->mailerSpy->sentMessages);
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_EDITOR));
        self::assertStringContainsString('Would notify recipient 2: 1 notification(s) across 1 record(s).', $this->tester->getDisplay());
    }

    #[Test]
    public function digestedAtIsOnlyEverSetForTheNotificationsARunActuallyDigested(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft');
        $this->createStatusChange(recipientUid: self::RECIPIENT_OPTED_OUT, crdate: 1000, previous: null, new: 'Draft');

        $this->tester->execute([]);

        self::assertCount(0, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_EDITOR));
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_OPTED_OUT));
    }

    #[Test]
    public function aTransportFailureForOneRecipientDoesNotAbortTheRunForOthers(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft');
        $this->createStatusChange(recipientUid: 1, crdate: 1000, previous: null, new: 'Draft');
        $this->mailerSpy->throwOnSend = true;

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertCount(0, $this->mailerSpy->sentMessages);
        // Neither recipient's notifications were digested: the mail was never actually sent.
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(self::RECIPIENT_EDITOR));
        self::assertCount(1, $this->notificationRepository->findPendingByRecipient(1));
        self::assertStringContainsString('failed 2', $this->tester->getDisplay());
    }

    #[Test]
    public function readNotificationsAreStillIncludedInTheDigest(): void
    {
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft');
        $this->markAllRowsRead();

        $this->tester->execute([]);

        self::assertCount(1, $this->mailerSpy->sentMessages);
    }

    #[Test]
    public function notificationsAboutRecordsTheRecipientMayNoLongerReadAreNotMailedOut(): void
    {
        // Page 4 is deliberately unreadable for the editor group (no show permission for
        // anyone but its owner). The digest aggregates up to a day, so access can be
        // withdrawn between writing the notification and sending the mail - and the payload
        // carries a title snapshot, so without the check the title would leave the backend.
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages_restricted.csv');
        $this->createStatusChange(recipientUid: self::RECIPIENT_EDITOR, crdate: 1000, previous: null, new: 'Draft', recordUid: 4);

        $this->tester->execute([]);

        self::assertSame([], $this->mailerSpy->sentMessages);
    }

    private function createContentChange(int $recipientUid, int $crdate, int $changeCount, array $actorUids, int $recordUid = 1): void
    {
        $this->notificationRepository->create(new Notification(
            $recipientUid,
            NotificationEventType::ContentChanged,
            'pages',
            $recordUid,
            null,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home', 'changeCount' => $changeCount, 'actorUids' => $actorUids],
            $crdate,
        ));
    }

    private function createStatusChange(int $recipientUid, int $crdate, ?string $previous, string $new, int $recordUid = 1): void
    {
        $this->notificationRepository->create(new Notification(
            $recipientUid,
            NotificationEventType::StatusChanged,
            'pages',
            $recordUid,
            1,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home', 'previousStatus' => $previous, 'newStatus' => $new],
            $crdate,
        ));
    }

    private function createAssigneeChange(int $recipientUid, int $crdate, ?int $previous, ?int $new, int $recordUid = 1): void
    {
        $this->notificationRepository->create(new Notification(
            $recipientUid,
            NotificationEventType::Assigned,
            'pages',
            $recordUid,
            1,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Child A', 'previousAssignee' => $previous, 'newAssignee' => $new],
            $crdate,
        ));
    }

    private function markAllRowsRead(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->update(Configuration::TABLE_NOTIFICATION, ['read_at' => time()], []);
    }
}
