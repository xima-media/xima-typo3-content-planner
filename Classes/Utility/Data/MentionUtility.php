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

namespace Xima\XimaTypo3ContentPlanner\Utility\Data;

use DOMDocument;
use DOMElement;
use DOMXPath;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;

/**
 * MentionUtility.
 *
 * Storage contract for @-mentions in comment content (issue #305). A mention is persisted
 * **inline, inside the comment's existing `content` HTML field** - no separate column or table -
 * as a marker element carrying the mentioned backend user's stable UID:
 *
 * ..  code-block:: html
 *
 *     <a class="ctp-mention" data-mention-uid="42">@display-name-at-mention-time</a>
 *
 * The UID, not the display name, is the source of truth: a user's name can change after the
 * mention was authored, so {@see self::renderContentWithMentions()} re-resolves it fresh on
 * every render rather than trusting the stored text.
 *
 * What that method renders is deliberately *not* a link. `be_users` is an adminOnly table, so a
 * `record_edit` link to the mentioned user is a dead end for everyone but administrators - the
 * large majority of the people who read a mention. The marker becomes a `<button>` instead,
 * which comment-mention-card.js turns into a profile card popover, and which - unlike an anchor
 * without an href - is reachable by keyboard.
 *
 * The other half of this contract lives in the composer: comment-mention.js produces the marker
 * through its Mention plugin's downcast converter and reads it back through the matching upcast
 * converter. {@see self::MARKER_CLASS} and {@see self::MARKER_ATTRIBUTE} are the two pieces both
 * sides have to agree on.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class MentionUtility
{
    /**
     * CSS class marking a persisted mention marker element. Matched regardless of any other
     * classes the element may carry, and regardless of its tag name (the contract above shows
     * `<a>`, but nothing here assumes that literally).
     */
    public const MARKER_CLASS = 'ctp-mention';

    /**
     * Data attribute on a marker element holding the mentioned backend user's stable UID.
     */
    public const MARKER_ATTRIBUTE = 'data-mention-uid';

    /**
     * @return list<int> unique, positive backend user UIDs, in document order
     */
    public static function extractMentionedUserUids(string $htmlContent): array
    {
        $dom = self::parseFragment($htmlContent);
        if (null === $dom) {
            return [];
        }

        $uids = [];
        foreach (self::findMentionMarkers($dom) as $marker) {
            $uid = (int) $marker->getAttribute(self::MARKER_ATTRIBUTE);
            if ($uid > 0) {
                $uids[$uid] = $uid;
            }
        }

        return array_values($uids);
    }

    /**
     * Resolves every marker's current display name (falling back to leaving a stale/unknown
     * marker untouched rather than dropping it) and turns it into the profile-card trigger the
     * frontend binds to. Safe to call on content with no markers at all - returned unchanged.
     */
    public static function renderContentWithMentions(string $htmlContent): string
    {
        $dom = self::parseFragment($htmlContent);
        if (null === $dom) {
            return $htmlContent;
        }

        $markers = self::findMentionMarkers($dom);
        if ([] === $markers) {
            return $htmlContent;
        }

        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        foreach ($markers as $marker) {
            self::refreshMarker($marker, $dom, $backendUserRepository);
        }

        return self::extractBodyInnerHtml($dom);
    }

    private static function refreshMarker(DOMElement $marker, DOMDocument $dom, BackendUserRepository $backendUserRepository): void
    {
        $uid = (int) $marker->getAttribute(self::MARKER_ATTRIBUTE);
        if ($uid <= 0) {
            return;
        }

        $displayName = $backendUserRepository->getDisplayNameByUid($uid);
        if ('' === $displayName) {
            // Deleted/unknown user: leave the stale marker as-is rather than losing the mention
            // entirely - the comment's history should not silently change meaning.
            return;
        }

        $marker->parentNode?->replaceChild(self::buildTrigger($dom, $uid, $displayName), $marker);
    }

    /**
     * The stored marker is an `<a>` - that is what survives RteHtmlParser on the way into the
     * database - but an anchor without an href is not focusable, so the rendered trigger is a
     * `<button>`. The element is rebuilt rather than renamed because DOM offers no rename.
     */
    private static function buildTrigger(DOMDocument $dom, int $uid, string $displayName): DOMElement
    {
        $isSelf = $uid === self::getCurrentBackendUserId();

        $trigger = $dom->createElement('button');
        $trigger->setAttribute('type', 'button');
        $trigger->setAttribute('class', self::MARKER_CLASS.($isSelf ? ' '.self::MARKER_CLASS.'--self' : ''));
        $trigger->setAttribute(self::MARKER_ATTRIBUTE, (string) $uid);
        $trigger->appendChild($dom->createTextNode('@'.$displayName));

        // The stronger tint alone would carry this information by colour only, which is lost to
        // screen readers and in forced-colors mode.
        $hint = $isSelf ? self::getSelfHint() : '';
        if ('' !== $hint) {
            $hintElement = $dom->createElement('span');
            $hintElement->setAttribute('class', 'visually-hidden');
            $hintElement->appendChild($dom->createTextNode(' '.$hint));
            $trigger->appendChild($hintElement);
        }

        return $trigger;
    }

    /**
     * Rendering a comment must not depend on a language service being around: this runs from
     * the public API too ({@see \Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility}), and a
     * CLI or scheduler context has no $GLOBALS['LANG']. Losing the hint there is a far better
     * outcome than a TypeError taking the whole comment down - the same reason the anchor this
     * replaced caught its missing-routing case.
     */
    private static function getSelfHint(): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if (!$languageService instanceof LanguageService) {
            return '';
        }

        return $languageService->sL(
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:mention.self',
        );
    }

    private static function getCurrentBackendUserId(): int
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication ? (int) ($backendUser->user['uid'] ?? 0) : 0;
    }

    /**
     * @return list<DOMElement> elements carrying both {@see self::MARKER_CLASS} (among possibly
     *                          other classes) and {@see self::MARKER_ATTRIBUTE}, in document order
     */
    private static function findMentionMarkers(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " '.self::MARKER_CLASS.' ") and @'.self::MARKER_ATTRIBUTE.']',
        );

        if (false === $nodes) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private static function parseFragment(string $htmlContent): ?DOMDocument
    {
        if ('' === trim($htmlContent)) {
            return null;
        }

        $dom = new DOMDocument();
        $previousLibXmlUseErrors = libxml_use_internal_errors(true);
        // The XML declaration forces DOMDocument to treat the fragment as UTF-8 without
        // mangling multi-byte characters - it is discarded by the parser, not rendered.
        $success = $dom->loadHTML('<?xml encoding="utf-8"?>'.$htmlContent);
        libxml_use_internal_errors($previousLibXmlUseErrors);

        return $success ? $dom : null;
    }

    private static function extractBodyInnerHtml(DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (null === $body) {
            return '';
        }

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
