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
}
