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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

use Doctrine\DBAL\Exception;
use JsonException;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\NotificationItem;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, NotificationRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Data\DiffUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * NotificationCenterDataProvider.
 *
 * Assembles the backend toolbar notification center's data (issue #301) from raw
 * `tx_ximatypo3contentplanner_notification` rows: unread count/badge, and the permission-checked
 * {@see NotificationItem} list for the dropdown.
 *
 * The title snapshot stored on a notification's payload at dispatch time
 * (see {@see NotificationPayloadFactory}) was resolved without regard to any particular
 * recipient's access - a notification is fanned out to every watcher regardless of who can
 * currently see the record. Rendering it verbatim would leak that title to a recipient who has
 * since lost access (or never had it, e.g. a page moved under a different mount point). Every
 * item is therefore re-checked against the *current* record and the *viewing* backend user via
 * {@see PermissionUtility::checkAccessForRecord()} here, falling back to a generic label and no
 * link when that check fails - this is what the acceptance criteria for #301 requires.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationCenterDataProvider
{
    private const DROPDOWN_LIMIT = 10;
    private const BADGE_CAP = 9;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly RecordRepository $recordRepository,
        private readonly BackendUserRepository $backendUserRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function getUnreadCount(int $backendUserUid): int
    {
        return $this->notificationRepository->countUnreadByRecipient($backendUserUid);
    }

    /**
     * Capped display value for the toolbar badge, e.g. "9+" once the real count exceeds it.
     *
     * @throws Exception
     */
    /**
     * Callers that already resolved the count should pass it in; every caller so far needs
     * both values, and looking it up again here doubled the COUNT query per request.
     */
    public function getUnreadBadgeLabel(int $backendUserUid, ?int $count = null): string
    {
        $count ??= $this->getUnreadCount($backendUserUid);

        return $count > self::BADGE_CAP ? self::BADGE_CAP.'+' : (string) $count;
    }

    /**
     * @return list<NotificationItem>
     *
     * @throws Exception
     */
    public function getLatestForDropdown(int $backendUserUid): array
    {
        $rows = $this->notificationRepository->findLatestByRecipient($backendUserUid, self::DROPDOWN_LIMIT);

        return array_map($this->buildItem(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildItem(array $row): NotificationItem
    {
        $table = (string) $row['tablename'];
        $recordUid = (int) $row['record_uid'];
        $eventType = NotificationEventType::tryFrom((string) $row['event_type']);
        $reason = NotificationReason::tryFrom((string) $row['reason']);
        $payload = $this->decodePayload($row['payload'] ?? null);

        [$title, $url] = $this->resolveTitleAndUrl($table, $recordUid, $payload);

        return new NotificationItem(
            (int) $row['uid'],
            null !== $eventType ? $eventType->value : (string) $row['event_type'],
            $this->resolveIcon($eventType),
            $title,
            $url,
            $this->resolveActorLabel($row['actor'] ?? null),
            $this->resolveReasonLabel($reason),
            DiffUtility::timeAgo((int) $row['crdate']),
            null !== ($row['read_at'] ?? null),
            $this->resolveChangeSummary($eventType, $payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveChangeSummary(?NotificationEventType $eventType, array $payload): ?string
    {
        if (NotificationEventType::ContentChanged !== $eventType) {
            return null;
        }

        $changeCount = (int) ($payload['changeCount'] ?? 0);
        if ($changeCount <= 0) {
            return null;
        }

        $actorUids = $payload['actorUids'] ?? null;
        $actorCount = is_array($actorUids) ? count($actorUids) : 0;

        return sprintf(
            $this->getLanguageService()->sL(self::languageLabel(
                1 === $actorCount ? 'notification.contentChanged.summary.singular' : 'notification.contentChanged.summary.plural',
            )),
            $actorCount,
            $changeCount,
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: string, 1: ?string}
     */
    private function resolveTitleAndUrl(string $table, int $recordUid, array $payload): array
    {
        $record = $this->recordRepository->findByUid($table, $recordUid, true);
        if (!is_array($record) || !PermissionUtility::checkAccessForRecord($table, $record)) {
            return [$this->getLanguageService()->sL(self::languageLabel('notification.record.inaccessible')), null];
        }

        // The payload's title snapshot (see NotificationPayloadFactory) is preferred once access
        // is confirmed: it keeps the entry readable even if the record was since renamed.
        $title = is_string($payload['title'] ?? null) && '' !== $payload['title']
            ? $payload['title']
            : ExtensionUtility::getTitle(ExtensionUtility::getTitleField($table), $record);

        try {
            $url = UrlUtility::getRecordLink($table, $recordUid, is_string($record['folder_identifier'] ?? null) ? $record['folder_identifier'] : null);
        } catch (RouteNotFoundException) {
            $url = null;
        }

        return [$title, $url];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (!is_string($payload) || '' === $payload) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveActorLabel(mixed $actor): string
    {
        if (null === $actor) {
            return $this->getLanguageService()->sL(self::languageLabel('notification.actor.system'));
        }

        $label = $this->backendUserRepository->getUsernameByUid((int) $actor);

        return '' !== $label ? $label : $this->getLanguageService()->sL(self::languageLabel('notification.actor.system'));
    }

    private function resolveReasonLabel(?NotificationReason $reason): string
    {
        if (null === $reason) {
            return '';
        }

        return $this->getLanguageService()->sL(self::languageLabel('notification.reason.'.$reason->value));
    }

    private function resolveIcon(?NotificationEventType $eventType): string
    {
        return match ($eventType) {
            NotificationEventType::StatusChanged => 'actions-flag-edit',
            NotificationEventType::Assigned => 'actions-assign-to-me',
            NotificationEventType::CommentAdded => 'actions-comment',
            NotificationEventType::ContentChanged => 'actions-document-edit',
            NotificationEventType::Mentioned => 'actions-tag',
            null => 'actions-info',
        };
    }

    private static function languageLabel(string $key): string
    {
        return 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:'.$key;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
