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

/**
 * TodoToggleUtility.
 *
 * Flips a single to-do checkbox inside a comment's rich-text content, for the inline toggle
 * (CP-30, #389) exposed by CommentEditorController::commentToggleTodoAction().
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class TodoToggleUtility
{
    /**
     * Flips the `checked` attribute of the Nth `<input type="checkbox">` in $content,
     * byte-for-byte identical otherwise. Loading through a wrapper element with
     * LIBXML_HTML_NOIMPLIED/LIBXML_HTML_NODEFDTD keeps libxml from adding an implied
     * <html>/<body> around the fragment; the leading processing instruction forces UTF-8
     * interpretation so multi-byte content (emoji, umlauts) round-trips without mojibake.
     *
     * @return string|null the updated content, or null if no checkbox exists at $todoIndex
     */
    public static function toggle(string $content, int $todoIndex, bool $checked): ?string
    {
        $dom = new DOMDocument();
        $previousLibXmlUseErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>'.$content.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($previousLibXmlUseErrors);

        $checkbox = self::findCheckboxByIndex($dom, $todoIndex);
        if (!$checkbox instanceof DOMElement) {
            return null;
        }

        if ($checked) {
            $checkbox->setAttribute('checked', 'checked');
        } else {
            $checkbox->removeAttribute('checked');
        }

        $result = '';
        foreach ($dom->documentElement->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    private static function findCheckboxByIndex(DOMDocument $dom, int $todoIndex): ?DOMElement
    {
        $index = 0;
        foreach ($dom->getElementsByTagName('input') as $input) {
            if ('checkbox' !== $input->getAttribute('type')) {
                continue;
            }

            if ($index === $todoIndex) {
                return $input;
            }

            ++$index;
        }

        return null;
    }
}
