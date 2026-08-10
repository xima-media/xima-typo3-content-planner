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
        // 'includeContentElements' is set explicitly here so this test verifies only
        // what its name says (empty-field omission), independent of that field's own
        // default - see testSerializeTreatsAMissingIncludeContentElementsKeyAsExcluded
        // for the missing-key case.
        $tokens = $this->subject->serialize([
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
            'includeContentElements' => ['1'],
        ]);

        self::assertSame([['key' => 'status', 'values' => ['2']]], $tokens);
    }

    public function testSerializeAppendsPagesOnlyMarkerWhenContentElementsExcluded(): void
    {
        $tokens = $this->subject->serialize([
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
            'includeContentElements' => [],
        ]);

        self::assertSame([['key' => 'status', 'values' => ['2', '_pages-only']]], $tokens);
    }

    public function testSerializeTreatsAMissingIncludeContentElementsKeyAsExcluded(): void
    {
        // Regression test: a checkbox-group the modal's own client-side state
        // builder drops entirely once nothing in it is checked (the key is
        // absent from $modalState, not present as []) - this must resolve the
        // same way as an explicitly-empty key, i.e. "unchecked". Defaulting the
        // missing-key case to ['1'] here previously meant unchecking the toggle
        // silently reverted to checked on every serialize() call, since the
        // modal never actually sends an empty array for it.
        $tokens = $this->subject->serialize([
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
            // 'includeContentElements' deliberately absent, not [].
        ]);

        self::assertSame([['key' => 'status', 'values' => ['2', '_pages-only']]], $tokens);
    }

    public function testHydrateReversesSerializeForMultipleTokens(): void
    {
        $modalState = [
            'status' => ['2', '3'],
            'assignee' => ['me'],
            'comments' => ['open'],
            'includeContentElements' => ['1'],
        ];

        $hydrated = $this->subject->hydrate($this->subject->serialize($modalState));

        self::assertSame($modalState, $hydrated);
    }

    public function testHydrateReversesSerializeWhenContentElementsExcluded(): void
    {
        $modalState = [
            'status' => ['2'],
            'assignee' => [],
            'comments' => [],
            'includeContentElements' => [],
        ];

        $hydrated = $this->subject->hydrate($this->subject->serialize($modalState));

        self::assertSame($modalState, $hydrated);
    }

    public function testHydrateDefaultsIncludeContentElementsToOnForUnknownTokens(): void
    {
        $hydrated = $this->subject->hydrate([]);

        self::assertSame(['1'], $hydrated['includeContentElements']);
    }

    public function testWithContentElementsMarkerAppendsMarkerWhenExcluded(): void
    {
        self::assertSame(['2', '_pages-only'], $this->subject->withContentElementsMarker(['2'], includeContentElements: false));
    }

    public function testWithContentElementsMarkerLeavesValuesUnchangedWhenIncluded(): void
    {
        self::assertSame(['2'], $this->subject->withContentElementsMarker(['2'], includeContentElements: true));
    }

    public function testExtractContentElementsFlagSplitsMarkerFromValues(): void
    {
        [$includeContentElements, $values] = $this->subject->extractContentElementsFlag(['2', '_pages-only']);

        self::assertFalse($includeContentElements);
        self::assertSame(['2'], $values);
    }

    public function testExtractContentElementsFlagDefaultsToTrueWithoutMarker(): void
    {
        [$includeContentElements, $values] = $this->subject->extractContentElementsFlag(['2']);

        self::assertTrue($includeContentElements);
        self::assertSame(['2'], $values);
    }

    public function testHydrateDoesNotPreserveContentElementsFlagWhenNoCriteriaAreSelected(): void
    {
        // Intentional behavior: When all three criteria (status, assignee, comments) are
        // empty, serialize() returns [] because no tokens are emitted for empty-values keys.
        // Then hydrate([]) defaults includeContentElements back to ['1'] (included), even
        // though the original state had it as [] (excluded). This is an accepted trade-off:
        // emitting a token just to preserve the toggle state would cause that empty-values
        // token to resolve to zero page uids and zero out the entire AND-intersection in
        // the page tree filter engine, hiding the tree entirely. Since no token is active
        // when all criteria are empty, filtering never happens regardless — the cosmetic
        // reset is preferable to the functional regression of hiding the tree.
        $originalState = [
            'status' => [],
            'assignee' => [],
            'comments' => [],
            'includeContentElements' => [],
        ];

        $serialized = $this->subject->serialize($originalState);
        self::assertSame([], $serialized, 'No tokens should be emitted when all criteria are empty');

        $hydrated = $this->subject->hydrate($serialized);
        self::assertSame(['1'], $hydrated['includeContentElements'], 'Toggle defaults back to included when no criteria are selected');
    }
}
