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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Localization\{LanguageService, LanguageServiceFactory};
use TYPO3\CMS\Core\Mail\MailerInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, ImmediateEmailQueueRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\{DigestGroupBuilder, DigestMailFactory};
use Xima\XimaTypo3ContentPlanner\Service\Notification\RecipientAccessChecker;

use function is_array;
use function is_string;

/**
 * ImmediateEmailService.
 *
 * Per-event counterpart to {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestService}'s
 * cron-triggered digest (issue #306): a recipient opted into the immediate channel (see
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\ImmediateEmailChannel}) gets a
 * separate mail per record, sent as soon as the first event on it arrives - but never more than
 * one per `(recipient, record)` pair per {@see self::THROTTLE_WINDOW_SECONDS}. Every event that
 * arrives while a record is still inside that window is queued rather than mailed on its own; the
 * next event to arrive once the window has passed flushes everything queued for that record into
 * a single batched mail.
 *
 * Deliberately reuses {@see DigestGroupBuilder} and {@see DigestMailFactory} unchanged: since both
 * only ever see the rows for one `(backend_user, tablename, record_uid)` triple here, they always
 * produce exactly one {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\DigestRecordGroup} and
 * render it through the very same `NotificationDigest` Fluid template the daily digest uses - "one
 * mail, one record's worth of deduped lines" either way.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ImmediateEmailService
{
    /**
     * 15 minutes - issue #306's "simple throttle" acceptance criterion. Anchored on the queue's
     * own `sent_at` column rather than injected, mirroring how
     * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Retention\NotificationRetentionService}
     * compares stored timestamps against a plain `time()` call.
     */
    private const THROTTLE_WINDOW_SECONDS = 900;

    /**
     * The per-record throttle above does not bound the total: touching many different watched
     * records in quick succession produces one mail each. This caps what a single recipient
     * can receive per hour no matter how many records are involved, so a bulk edit cannot turn
     * into a mailbox flood. Anything beyond the cap stays queued and goes out with the next
     * flush once the hour has passed.
     */
    private const GLOBAL_LIMIT_PER_WINDOW = 12;

    private const GLOBAL_LIMIT_WINDOW_SECONDS = 3600;

    /**
     * How long a claim ({@see ImmediateEmailQueueRepository::claimPending()}) may sit without
     * reaching {@see ImmediateEmailQueueRepository::markSent()} before another call is allowed to
     * reclaim the same rows. Bounds how long a crashed/thrown-mid-send process can block its
     * queue rows from ever being retried.
     */
    private const CLAIM_LEASE_SECONDS = 300;

    public function __construct(
        private readonly ImmediateEmailQueueRepository $queueRepository,
        private readonly DigestGroupBuilder $groupBuilder,
        private readonly DigestMailFactory $mailFactory,
        private readonly MailerInterface $mailer,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly RecordRepository $recordRepository,
        private readonly RecipientAccessChecker $accessChecker,
    ) {}

    /**
     * @param array<string, mixed> $recipient be_users row, already validated eligible by the
     *                                        caller (see `ImmediateEmailChannel::supports()`)
     *
     * @throws Exception
     */
    public function handle(Notification $notification, array $recipient): void
    {
        $this->queueRepository->enqueue($notification);

        $this->attemptFlush($notification->getRecipientUid(), $notification->getTable(), $notification->getRecordUid(), $recipient);
    }

    /**
     * Re-checks every recipient/record pair that still has unsent queue rows and flushes the
     * ones whose throttle window has now elapsed and whose hourly cap has now reset - the only
     * way a batch that was queued (not sent) because of either limit ever gets flushed once no
     * further live event arrives on that same record. Intended to run on a schedule (e.g. every
     * few minutes) alongside `content-planner:notification:digest`.
     *
     * @return int number of triples actually flushed
     *
     * @throws Exception
     */
    public function flushDueQueues(): int
    {
        $flushed = 0;

        foreach ($this->queueRepository->findDistinctPendingTriples() as $triple) {
            $recipient = $this->backendUserRepository->findByUid($triple['backend_user']);
            if (!is_array($recipient)) {
                continue;
            }

            if ($this->attemptFlush($triple['backend_user'], $triple['tablename'], $triple['record_uid'], $recipient)) {
                ++$flushed;
            }
        }

        return $flushed;
    }

    /**
     * @param array<string, mixed> $recipient
     *
     * @throws Exception
     */
    private function attemptFlush(int $backendUserUid, string $table, int $recordUid, array $recipient): bool
    {
        $lastSentAt = $this->queueRepository->findLastSentAt($backendUserUid, $table, $recordUid);

        if (null !== $lastSentAt && (time() - $lastSentAt) < self::THROTTLE_WINDOW_SECONDS) {
            // Still inside the throttle window: queued, flushed together with this event the
            // next time one arrives after the window has passed (or by flushDueQueues()).
            return false;
        }

        $sentInWindow = $this->queueRepository->countSentSince($backendUserUid, time() - self::GLOBAL_LIMIT_WINDOW_SECONDS);

        if ($sentInWindow >= self::GLOBAL_LIMIT_PER_WINDOW) {
            return false;
        }

        return $this->flush($backendUserUid, $table, $recordUid, $recipient);
    }

    /**
     * @param array<string, mixed> $recipient
     *
     * @throws Exception
     */
    private function flush(int $backendUserUid, string $table, int $recordUid, array $recipient): bool
    {
        $rows = $this->queueRepository->findPending($backendUserUid, $table, $recordUid);

        if ([] === $rows) {
            return false;
        }

        if (!$this->hasRecordAccess($backendUserUid, $table, $recordUid)) {
            // The recipient's access to the record was revoked since these rows were queued.
            // Leave them claimed-never/unsent so retention (issue #304) eventually sweeps them
            // rather than emailing content this recipient may no longer read.
            return false;
        }

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $rows);

        // Claim before sending, not after: two concurrent saves can both find the same pending
        // rows, and each gets back only the exact subset its own claim actually touched -
        // never a superset it merely observed. A transport failure below leaves the claim in
        // place; it becomes reclaimable once CLAIM_LEASE_SECONDS has passed, so the mail is
        // retried rather than silently dropped.
        $claimedRows = $this->queueRepository->claimPending($uids, time() - self::CLAIM_LEASE_SECONDS);

        if ([] === $claimedRows) {
            return false;
        }

        $this->sendInRecipientLanguage($recipient, $claimedRows);

        $claimedUids = array_map(static fn (array $row): int => (int) $row['uid'], $claimedRows);
        $this->queueRepository->markSent($claimedUids, time());

        return true;
    }

    /**
     * @throws Exception
     */
    private function hasRecordAccess(int $backendUserUid, string $table, int $recordUid): bool
    {
        // Folder status rows are not TCA records and carry no page to check against, matching
        // DigestService::filterReadableRows()'s handling of the same case.
        if (Configuration::TABLE_FOLDER === $table) {
            return true;
        }

        $record = $this->recordRepository->findByUid($table, $recordUid, true);

        return is_array($record) && $this->accessChecker->canAccess($backendUserUid, $table, $record);
    }

    /**
     * @param array<string, mixed>       $recipient
     * @param list<array<string, mixed>> $rows      raw queue rows, `payload` still a JSON string
     */
    private function sendInRecipientLanguage(array $recipient, array $rows): void
    {
        $previousLanguage = $GLOBALS['LANG'] ?? null;
        $GLOBALS['LANG'] = $this->languageServiceFactory->create($this->resolveUserLanguage($recipient));

        try {
            $groups = $this->groupBuilder->build(
                array_map($this->decodeRow(...), $rows),
                fn (mixed $uid): string => $this->resolveAssigneeLabel($uid),
            );

            $this->mailer->send($this->mailFactory->build($recipient, $groups));
        } finally {
            $GLOBALS['LANG'] = $previousLanguage;
        }
    }

    /**
     * @param array<string, mixed> $recipient
     */
    private function resolveUserLanguage(array $recipient): string
    {
        return is_string($recipient['lang'] ?? null) && '' !== $recipient['lang'] ? $recipient['lang'] : 'default';
    }

    /**
     * @param array<string, mixed> $row raw queue row, `payload` still a JSON string
     *
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        $payload = $row['payload'] ?? null;
        $decoded = is_string($payload) && '' !== $payload ? json_decode($payload, true) : null;
        $row['payload'] = is_array($decoded) ? $decoded : [];

        return $row;
    }

    private function resolveAssigneeLabel(mixed $uid): string
    {
        $uid = null !== $uid ? (int) $uid : 0;
        if (0 === $uid) {
            return $this->getUnassignedLabel();
        }

        $label = $this->backendUserRepository->getDisplayNameByUid($uid);

        return '' !== $label ? $label : $this->getUnassignedLabel();
    }

    private function getUnassignedLabel(): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:digest.mail.unassigned');
    }

    /**
     * Typed accessor for `$GLOBALS['LANG']` (an untyped superglobal entry), set to the
     * recipient's own language for the duration of {@see self::sendInRecipientLanguage()}.
     */
    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
