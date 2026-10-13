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

use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;

use function is_array;
use function is_string;
use function sprintf;

/**
 * NotificationSentenceBuilder.
 *
 * Splits an entry of the toolbar notification center into the words around the record, so the
 * template can set the record in bold: "{actor} {before} **{record}** {after}". It also decides
 * which tag says why the entry exists, whether it is "for you" (a mention, or an assignment to the
 * viewer) and which modal tab a click should open.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationSentenceBuilder
{
    public const TAB_COMMENTS = 'comments';
    public const TAB_ASSIGNEE = 'assignee';

    public function __construct(private readonly BackendUserRepository $backendUserRepository) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{verbBefore: string, verbAfter: string, tagLabel: string, tagHighlight: bool, forYou: bool, modalTab: string}
     */
    public function describe(?NotificationEventType $eventType, array $payload, int $viewerUid): array
    {
        $assignedToViewer = NotificationEventType::Assigned === $eventType && (int) ($payload['newAssignee'] ?? 0) === $viewerUid;
        $forYou = NotificationEventType::Mentioned === $eventType || $assignedToViewer;

        return [
            'verbBefore' => $this->verbBefore($eventType, $payload),
            'verbAfter' => $this->verbAfter($eventType, $payload, $assignedToViewer),
            'tagLabel' => $this->label(match (true) {
                NotificationEventType::Mentioned === $eventType => 'notification.tag.mentioned',
                $assignedToViewer => 'notification.tag.assigned',
                default => 'notification.tag.watching',
            }),
            'tagHighlight' => $forYou,
            'forYou' => $forYou,
            'modalTab' => NotificationEventType::Assigned === $eventType ? self::TAB_ASSIGNEE : self::TAB_COMMENTS,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verbBefore(?NotificationEventType $eventType, array $payload): string
    {
        return match ($eventType) {
            NotificationEventType::Mentioned => $this->label('notification.verb.mentioned.before'),
            NotificationEventType::Assigned => $this->label('notification.verb.assigned.before'),
            NotificationEventType::StatusChanged => $this->label('notification.verb.status.before'),
            NotificationEventType::CommentAdded => $this->label('notification.verb.comment.before'),
            NotificationEventType::ContentChanged => sprintf($this->label(1 === (int) ($payload['changeCount'] ?? 0) ? 'notification.verb.content.singular' : 'notification.verb.content.plural'), (int) ($payload['changeCount'] ?? 0)),
            null => '',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verbAfter(?NotificationEventType $eventType, array $payload, bool $assignedToViewer): string
    {
        if (NotificationEventType::Assigned === $eventType) {
            return $assignedToViewer ? $this->label('notification.verb.assigned.afterYou') : $this->assignedToSomeoneElse($payload);
        }

        if (NotificationEventType::Mentioned === $eventType) {
            return $this->label('notification.verb.mentioned.after');
        }

        if (NotificationEventType::CommentAdded === $eventType) {
            return $this->label('notification.verb.comment.after');
        }

        if (NotificationEventType::StatusChanged === $eventType) {
            $status = $payload['newStatus'] ?? null;

            return is_string($status) && '' !== $status
                ? sprintf($this->label('notification.verb.status.after'), $status)
                : $this->label('notification.verb.status.removed');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assignedToSomeoneElse(array $payload): string
    {
        $assignee = (int) ($payload['newAssignee'] ?? 0);
        $user = $assignee > 0 ? $this->backendUserRepository->findByUid($assignee) : false;
        $name = is_array($user) ? (string) ($user['realName'] ?: $user['username']) : '';

        return '' === $name ? $this->label('notification.verb.assigned.afterNobody') : sprintf($this->label('notification.verb.assigned.afterOther'), $name);
    }

    private function label(string $key): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:'.$key);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
