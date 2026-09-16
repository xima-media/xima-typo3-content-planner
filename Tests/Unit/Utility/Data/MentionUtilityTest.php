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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Data;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Data\MentionUtility;

/**
 * MentionUtilityTest.
 *
 * Covers only the pure, DB-free half of {@see MentionUtility} - parsing the persisted marker
 * HTML into stable UIDs. The DB-backed rendering half ({@see MentionUtility::renderContentWithMentionLinks()})
 * is covered by the functional counterpart, since it resolves current display names via
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class MentionUtilityTest extends TestCase
{
    #[Test]
    public function extractMentionedUserUidsReturnsEmptyArrayForEmptyContent(): void
    {
        self::assertSame([], MentionUtility::extractMentionedUserUids(''));
    }

    #[Test]
    public function extractMentionedUserUidsReturnsEmptyArrayWhenNoMarkersPresent(): void
    {
        self::assertSame([], MentionUtility::extractMentionedUserUids('<p>Just a plain comment.</p>'));
    }

    #[Test]
    public function extractMentionedUserUidsFindsASingleMarker(): void
    {
        $content = '<p>Hey <a class="ctp-mention" data-mention-uid="42">@John Doe</a>, please check this.</p>';

        self::assertSame([42], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsFindsMultipleMarkersInDocumentOrder(): void
    {
        $content = '<p>'
            .'<a class="ctp-mention" data-mention-uid="7">@Bob</a> and '
            .'<a class="ctp-mention" data-mention-uid="3">@Alice</a>'
            .'</p>';

        self::assertSame([7, 3], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsDeduplicatesTheSameUid(): void
    {
        $content = '<p>'
            .'<a class="ctp-mention" data-mention-uid="42">@John</a> ping '
            .'<a class="ctp-mention" data-mention-uid="42">@John</a> again'
            .'</p>';

        self::assertSame([42], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsMatchesTheMarkerClassAmongOtherClasses(): void
    {
        $content = '<a class="some-other-class ctp-mention another-class" data-mention-uid="9">@X</a>';

        self::assertSame([9], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsIgnoresElementsWithoutTheMarkerAttribute(): void
    {
        $content = '<a class="ctp-mention">@Missing attribute</a>';

        self::assertSame([], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsIgnoresElementsWithTheAttributeButWithoutTheMarkerClass(): void
    {
        $content = '<a data-mention-uid="42">@Not a mention marker</a>';

        self::assertSame([], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsIgnoresNonPositiveUids(): void
    {
        $content = '<p>'
            .'<a class="ctp-mention" data-mention-uid="0">@Zero</a>'
            .'<a class="ctp-mention" data-mention-uid="-1">@Negative</a>'
            .'<a class="ctp-mention" data-mention-uid="abc">@NotANumber</a>'
            .'</p>';

        self::assertSame([], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function extractMentionedUserUidsToleratesMalformedHtml(): void
    {
        $content = '<p>Unclosed <a class="ctp-mention" data-mention-uid="5">@Broken</p>';

        self::assertSame([5], MentionUtility::extractMentionedUserUids($content));
    }

    #[Test]
    public function markerConstantsMatchTheDocumentedContract(): void
    {
        self::assertSame('ctp-mention', MentionUtility::MARKER_CLASS);
        self::assertSame('data-mention-uid', MentionUtility::MARKER_ATTRIBUTE);
    }
}
