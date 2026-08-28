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
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

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
 * mention was authored, so {@see self::renderContentWithMentionLinks()} re-resolves it fresh on
 * every render rather than trusting the stored text.
 *
 * This is the exact contract a future CKEditor5 comment composer (issue #327, landed in a
 * separate branch stack - see this issue's PR description for the full cross-stack note) needs
 * to produce via its Mention plugin's downcast converter (and read back via an upcast converter
 * for editing) - {@see self::MARKER_CLASS} and {@see self::MARKER_ATTRIBUTE} are the two pieces
 * of that contract, kept as named constants specifically so that future config can reference
 * them instead of duplicating the literal strings.
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
     * marker untouched rather than dropping it) and refreshes its link target. Safe to call on
     * content with no markers at all - returned unchanged.
     */
    public static function renderContentWithMentionLinks(string $htmlContent): string
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

        while (null !== $marker->firstChild) {
            $marker->removeChild($marker->firstChild);
        }
        $marker->appendChild($dom->createTextNode('@'.$displayName));

        try {
            $marker->setAttribute('href', UrlUtility::getRecordLink('be_users', $uid));
        } catch (RouteNotFoundException) {
            // No backend routing available (e.g. a non-web context) - render the mention text
            // without a link rather than failing the whole comment render.
        }
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
