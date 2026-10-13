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
use Exception;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function iterator_to_array;
use function libxml_use_internal_errors;
use function preg_match;
use function str_contains;
use function str_starts_with;

/**
 * CommentLinkUtility.
 *
 * The link browser stores a link as `t3://page?uid=2`, which no browser can follow. Rendering a
 * comment turns such a link into a backend URL, opens external links in a new tab and drops
 * anything with a scheme other than http, https, mailto and tel.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentLinkUtility
{
    public static function resolveLinks(string $htmlContent): string
    {
        if (!str_contains($htmlContent, 'href=')) {
            return $htmlContent;
        }

        $dom = new DOMDocument();
        $previousLibXmlUseErrors = libxml_use_internal_errors(true);
        $success = $dom->loadHTML('<?xml encoding="utf-8"?>'.$htmlContent);
        libxml_use_internal_errors($previousLibXmlUseErrors);
        if (!$success) {
            return $htmlContent;
        }

        foreach (iterator_to_array($dom->getElementsByTagName('a')) as $anchor) {
            if (!$anchor->hasAttribute('href')) {
                continue;
            }

            $url = self::toUrl($anchor->getAttribute('href'));
            if (null === $url) {
                $anchor->removeAttribute('href');
                continue;
            }

            $anchor->setAttribute('href', $url);
            if (1 === preg_match('#^https?://#i', $url)) {
                $anchor->setAttribute('target', '_blank');
                $anchor->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $html = '';
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    private static function toUrl(string $href): ?string
    {
        if (str_starts_with($href, 't3://')) {
            return self::resolveTypo3Link($href);
        }

        return 1 === preg_match('#^(https?://|mailto:|tel:|/)#i', $href) ? $href : null;
    }

    private static function resolveTypo3Link(string $href): ?string
    {
        try {
            $data = GeneralUtility::makeInstance(LinkService::class)->resolve($href);
            if ('page' === $data['type']) {
                return (string) GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => (int) $data['pageuid']]);
            }
            if ('url' === $data['type']) {
                return self::toUrl((string) $data['url']);
            }
            if ('file' === $data['type'] && $data['file'] instanceof FileInterface) {
                return $data['file']->getPublicUrl();
            }
        } catch (Exception) {
            // An unresolvable link is shown as plain text.
        }

        return null;
    }
}
