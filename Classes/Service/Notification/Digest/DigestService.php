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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Digest;

use Doctrine\DBAL\Exception;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use TYPO3\CMS\Core\Localization\{LanguageService, LanguageServiceFactory};
use TYPO3\CMS\Core\Mail\MailerInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\DigestRunResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, NotificationRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\RecipientAccessChecker;

use function array_key_exists;
use function count;
use function is_array;
use function is_string;

/**
 * DigestService.
 *
 * Orchestrates one recipient's turn of the `content-planner:notification:digest` command (issue
 * #302): validates the recipient (still exists, opted in, has an email address), collects their
 * non-digested notifications, groups/dedupes them via {@see DigestGroupBuilder}, sends the mail
 * via {@see DigestMailFactory}, and finally marks exactly those notifications digested.
 *
 * The mail is sent *before* `digested_at` is set, deliberately: a crash or transport failure
 * between the two simply leaves the notifications pending for the next run rather than losing
 * them, since {@see NotificationRepository::markDigestedByUids()} is scoped to the uids this
 * run actually rendered into that mail.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DigestService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly DigestGroupBuilder $groupBuilder,
        private readonly DigestMailFactory $mailFactory,
        private readonly MailerInterface $mailer,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly RecordRepository $recordRepository,
        private readonly RecipientAccessChecker $accessChecker,
    ) {}

    /**
     * @throws Exception
     */
    public function processRecipient(int $backendUserUid, bool $dryRun): DigestRunResult
    {
        $recipient = $this->backendUserRepository->findByUid($backendUserUid);
        if (!is_array($recipient) || (bool) ($recipient['deleted'] ?? false) || (bool) ($recipient['disable'] ?? false)) {
            return DigestRunResult::skipped($backendUserUid, 'recipient no longer exists or is disabled');
        }

        if (!$this->hasOptedIn($recipient)) {
            return DigestRunResult::skipped($backendUserUid, 'opted out of the email digest');
        }

        if ($this->prefersImmediateEmail($recipient)) {
            return DigestRunResult::skipped($backendUserUid, 'using the immediate email channel instead of the daily digest');
        }

        $email = is_string($recipient['email'] ?? null) ? trim($recipient['email']) : '';
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->logger?->warning('Skipping content planner email digest: backend user has no valid email address', [
                'backendUser' => $backendUserUid,
            ]);

            return DigestRunResult::skipped($backendUserUid, 'no valid email address');
        }

        $rows = $this->filterReadableRows($backendUserUid, $this->notificationRepository->findPendingByRecipient($backendUserUid));
        if ([] === $rows) {
            return DigestRunResult::skipped($backendUserUid, 'nothing to digest');
        }

        return $this->digestInRecipientLanguage($recipient, $rows, $dryRun);
    }

    /**
     * Builds the groups and, on a real run, renders/sends the mail - all with $GLOBALS['LANG']
     * pointed at this recipient's own backend language for the whole duration, so every resolved
     * label (the "Unassigned" placeholder included) ends up in the right language.
     *
     * @param array<string, mixed>       $recipient
     * @param list<array<string, mixed>> $rows
     *
     * @throws Exception
     */
    private function digestInRecipientLanguage(array $recipient, array $rows, bool $dryRun): DigestRunResult
    {
        $backendUserUid = (int) $recipient['uid'];
        $previousLanguage = $GLOBALS['LANG'] ?? null;
        $GLOBALS['LANG'] = $this->languageServiceFactory->create($this->resolveUserLanguage($recipient));

        try {
            $groups = $this->groupBuilder->build(
                array_map($this->decodeRow(...), $rows),
                fn (mixed $uid): string => $this->resolveAssigneeLabel($uid),
            );

            if (!$dryRun) {
                $this->mailer->send($this->mailFactory->build($recipient, $groups));
            }
        } finally {
            $GLOBALS['LANG'] = $previousLanguage;
        }

        if ($dryRun) {
            return DigestRunResult::wouldSend($backendUserUid, count($rows), count($groups));
        }

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $rows);
        $this->notificationRepository->markDigestedByUids($uids, $backendUserUid);

        return DigestRunResult::sent($backendUserUid, count($rows), count($groups));
    }

    /**
     * Drops notifications about records the recipient may no longer read.
     *
     * The digest aggregates up to a day of activity, so access can be withdrawn between the
     * notification being written and the mail going out. The payload carries a title
     * snapshot, so without this the record title would leave the backend anyway - and unlike
     * the notification centre, a mail cannot re-check anything at the moment it is read.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    private function filterReadableRows(int $backendUserUid, array $rows): array
    {
        $readable = [];

        foreach ($rows as $row) {
            $table = (string) $row['tablename'];

            // Folder status rows are not TCA records and carry no page to check against.
            if (Configuration::TABLE_FOLDER === $table) {
                $readable[] = $row;

                continue;
            }

            $record = $this->recordRepository->findByUid($table, (int) $row['record_uid'], true);

            if (is_array($record) && $this->accessChecker->canAccess($backendUserUid, $table, $record)) {
                $readable[] = $row;
            }
        }

        return $readable;
    }

    /**
     * @param array<string, mixed> $recipient
     */
    private function resolveUserLanguage(array $recipient): string
    {
        return is_string($recipient['lang'] ?? null) && '' !== $recipient['lang'] ? $recipient['lang'] : 'default';
    }

    /**
     * @param array<string, mixed> $recipient
     */
    private function hasOptedIn(array $recipient): bool
    {
        return !array_key_exists(Configuration::FIELD_USER_DIGEST, $recipient) || (bool) $recipient[Configuration::FIELD_USER_DIGEST];
    }

    /**
     * Issue #306's second user toggle: a recipient on the immediate channel already gets a
     * separate mail per record as it happens, so the daily digest must skip them - otherwise
     * they would be notified twice for the same event.
     *
     * @param array<string, mixed> $recipient
     */
    private function prefersImmediateEmail(array $recipient): bool
    {
        return (bool) ($recipient[Configuration::FIELD_USER_IMMEDIATE_EMAIL] ?? false);
    }

    /**
     * @param array<string, mixed> $row raw notification row, `payload` still a JSON string
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
     * recipient's own language for the duration of {@see self::digestInRecipientLanguage()}.
     */
    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
