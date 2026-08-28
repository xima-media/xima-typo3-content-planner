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

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\DigestRecordGroup;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

use function count;
use function is_array;
use function sprintf;

/**
 * DigestMailFactory.
 *
 * Turns one recipient's deduped {@see DigestRecordGroup} list into a ready-to-send
 * {@see FluidEmail} for issue #302's email digest, using the `NotificationDigest` template (see
 * `Resources/Private/Templates/Mail/`, overridable per
 * `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths']`).
 *
 * Reads `$GLOBALS['LANG']` for every label it resolves (record reason, per-event-type lines,
 * subject) rather than accepting a `LanguageService` argument, mirroring
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider} - the
 * caller ({@see DigestService}) is responsible for pointing it at the recipient's backend
 * language before calling {@see self::build()}.
 *
 * Every value placed into the template is either a translated label (safe) or a record
 * title/comment excerpt straight from the notification payload - never marked for raw HTML
 * output, so Fluid's default escaping still applies in the HTML part.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DigestMailFactory
{
    public function __construct(private readonly RecordRepository $recordRepository) {}

    /**
     * @param array<string, mixed>    $recipient be_users row
     * @param list<DigestRecordGroup> $groups
     */
    public function build(array $recipient, array $groups): FluidEmail
    {
        $languageService = $this->getLanguageService();

        $viewGroups = array_map(
            fn (DigestRecordGroup $group): array => $this->buildViewGroup($group, $languageService),
            $groups,
        );

        $recipientName = '' !== (string) ($recipient['realName'] ?? '') ? (string) $recipient['realName'] : (string) ($recipient['username'] ?? '');

        $email = GeneralUtility::makeInstance(FluidEmail::class)->setTemplate('NotificationDigest');
        $email->to((string) $recipient['email']);
        $email->subject(sprintf($languageService->sL(self::languageLabel('digest.mail.subject')), count($groups)));
        $email->assignMultiple([
            'greeting' => sprintf($languageService->sL(self::languageLabel('digest.mail.greeting')), $recipientName),
            'groups' => $viewGroups,
        ]);

        return $email;
    }

    /**
     * @return array{title: string, url: string|null, reasonLabel: string, lines: list<string>, eventCount: int}
     */
    private function buildViewGroup(DigestRecordGroup $group, LanguageService $languageService): array
    {
        $lines = [];
        if ([] !== $group->getStatusChain()) {
            $lines[] = sprintf($languageService->sL(self::languageLabel('digest.mail.line.status')), implode(' → ', $group->getStatusChain()));
        }
        if ([] !== $group->getAssigneeChain()) {
            $lines[] = sprintf($languageService->sL(self::languageLabel('digest.mail.line.assignee')), implode(' → ', $group->getAssigneeChain()));
        }
        if (null !== $group->getLatestCommentExcerpt()) {
            $lines[] = sprintf($languageService->sL(self::languageLabel('digest.mail.line.comment')), $group->getLatestCommentExcerpt());
        }

        return [
            'title' => '' !== $group->getTitle() ? $group->getTitle() : $languageService->sL(self::languageLabel('digest.mail.record.unresolved')),
            'url' => $this->resolveUrl($group),
            'reasonLabel' => $languageService->sL(self::languageLabel('notification.reason.'.$group->getReason())),
            'lines' => $lines,
            'eventCount' => $group->getEventCount(),
        ];
    }

    /**
     * Folder deep links need a `folder_identifier`, which {@see RecordRepository::findByUid()}
     * never selects for any table - out of scope here, folders simply render without a link.
     */
    private function resolveUrl(DigestRecordGroup $group): ?string
    {
        if (Configuration::TABLE_FOLDER === $group->getTable()) {
            return null;
        }

        $record = $this->recordRepository->findByUid($group->getTable(), $group->getRecordUid(), true);
        if (!is_array($record)) {
            return null;
        }

        try {
            $relativeUrl = UrlUtility::getRecordLink($group->getTable(), $group->getRecordUid());
        } catch (RouteNotFoundException) {
            return null;
        }

        $baseUrl = ExtensionUtility::getNotificationDigestBackendBaseUrl();

        return '' !== $baseUrl ? $baseUrl.$relativeUrl : $relativeUrl;
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
