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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Integration\PagetreeFacets;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacetStateMapper;

/**
 * ContentPlannerFacetStateMapperTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentPlannerFacetStateMapperTest extends TestCase
{
    private ContentPlannerFacetStateMapper $subject;

    protected function setUp(): void
    {
        $this->subject = new ContentPlannerFacetStateMapper();
    }

    public function testSerializeOmitsTokensForEmptyFields(): void
    {
        $tokens = $this->subject->serialize(['status' => ['2'], 'assignee' => [], 'comments' => []]);

        self::assertSame([['key' => 'status', 'values' => ['2']]], $tokens);
    }

    public function testSerializeLeavesValuesUnchangedWhenPagesOnlyIsAbsent(): void
    {
        // "pagesOnly" absent means "not restricted" (content elements included) -
        // both the native default for an unchecked/untouched checkbox-group and the
        // desired filtering default, so this needs no special-case default the way
        // the old, inverted "includeContentElements" field did.
        $tokens = $this->subject->serialize([
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
        ]);

        self::assertSame([['key' => 'status', 'values' => ['2']]], $tokens);
    }

    public function testSerializeAppendsPagesOnlyMarkerWhenPagesOnlyIsChecked(): void
    {
        $tokens = $this->subject->serialize([
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
            'pagesOnly' => ['1'],
        ]);

        self::assertSame([['key' => 'status', 'values' => ['2', '_pages only']]], $tokens);
    }

    public function testHydrateReversesSerializeForMultipleTokens(): void
    {
        $modalState = [
            'status' => ['2', '3'],
            'assignee' => ['me'],
            'comments' => ['open'],
            'pagesOnly' => [],
        ];

        $hydrated = $this->subject->hydrate($this->subject->serialize($modalState));

        self::assertSame($modalState, $hydrated);
    }

    public function testHydrateReversesSerializeWhenPagesOnlyIsChecked(): void
    {
        $modalState = [
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
            'pagesOnly' => ['1'],
        ];

        $hydrated = $this->subject->hydrate($this->subject->serialize($modalState));

        self::assertSame($modalState, $hydrated);
    }

    public function testHydrateDefaultsPagesOnlyToUncheckedForUnknownTokens(): void
    {
        $hydrated = $this->subject->hydrate([]);

        self::assertSame([], $hydrated['pagesOnly']);
    }

    public function testWithContentElementsMarkerAppendsMarkerWhenExcluded(): void
    {
        self::assertSame(['2', '_pages only'], $this->subject->withContentElementsMarker(['2'], includeContentElements: false));
    }

    public function testWithContentElementsMarkerLeavesValuesUnchangedWhenIncluded(): void
    {
        self::assertSame(['2'], $this->subject->withContentElementsMarker(['2'], includeContentElements: true));
    }

    public function testPagesOnlyMarkerContainsWhitespace(): void
    {
        // Regression guard: typo3-pagetree-facets' TokenSerializer only quotes a
        // token's comma-joined value string when it contains whitespace (or a
        // literal quote); its own toolbar badge then counts a quoted value as one
        // active criterion instead of splitting it on comma. A marker without
        // whitespace (e.g. a hyphen) would NOT get quoted and would inflate that
        // count by one whenever this marker is present - confirmed live, see the
        // class docblock. This test exists so a future edit cannot silently swap
        // the marker back to something unquoted without a test failing.
        $marker = $this->subject->withContentElementsMarker([], includeContentElements: false)[0];

        self::assertMatchesRegularExpression('/\s/', $marker);
    }

    public function testExtractContentElementsFlagSplitsMarkerFromValues(): void
    {
        [$includeContentElements, $values] = $this->subject->extractContentElementsFlag(['2', '_pages only']);

        self::assertFalse($includeContentElements);
        self::assertSame(['2'], $values);
    }

    public function testExtractContentElementsFlagDefaultsToTrueWithoutMarker(): void
    {
        [$includeContentElements, $values] = $this->subject->extractContentElementsFlag(['2']);

        self::assertTrue($includeContentElements);
        self::assertSame(['2'], $values);
    }

    public function testHydrateDoesNotPreserveThePagesOnlyFlagWhenNoCriteriaAreSelected(): void
    {
        // Intentional behavior: When all three criteria (status, assignee, comments) are
        // empty, serialize() returns [] because no tokens are emitted for empty-values keys.
        // Then hydrate([]) defaults pagesOnly back to [] (unchecked/not restricted), even
        // though the original state had it checked. This is an accepted trade-off: emitting
        // a token just to preserve the toggle state would cause that empty-values token to
        // resolve to zero page uids and zero out the entire AND-intersection in the page
        // tree filter engine, hiding the tree entirely. Since no token is active when all
        // criteria are empty, filtering never happens regardless — the cosmetic reset is
        // preferable to the functional regression of hiding the tree.
        $originalState = [
            'status' => [],
            'assignee' => [],
            'comments' => [],
            'pagesOnly' => ['1'],
        ];

        $serialized = $this->subject->serialize($originalState);
        self::assertSame([], $serialized, 'No tokens should be emitted when all criteria are empty');

        $hydrated = $this->subject->hydrate($serialized);
        self::assertSame([], $hydrated['pagesOnly'], 'Toggle defaults back to unchecked when no criteria are selected');
    }
}
