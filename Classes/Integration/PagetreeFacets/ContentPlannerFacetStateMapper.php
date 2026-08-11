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

namespace Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets;

use function array_diff;
use function array_map;
use function array_merge;
use function array_values;
use function in_array;
use function is_array;

/**
 * ContentPlannerFacetStateMapper.
 *
 * Maps modal state to/from a plain token shape (array{key: string, values:
 * list<string>}) instead of the real Token class from
 * konradmichalik/typo3-pagetree-facets, so this logic stays unit-testable
 * without that suggest-only package installed. ContentPlannerFacet converts
 * to/from the real Token type at the boundary.
 *
 * The shared "pages only" modal toggle has no token key of its own - each of
 * the status/assignee/comments tokens is resolved independently by the
 * engine, so the only way for one modal field to influence all three is to
 * bake a sentinel value into every token's own values when content elements
 * are excluded (mirrors Token::FREETEXT's leading-underscore convention for
 * non-criterion values).
 *
 * The modal field is named "pagesOnly" and defaults to UNCHECKED, not
 * "includeContentElements" defaulting to checked as an earlier version of
 * this class had it. Both encode the identical default filtering behavior
 * (content elements included unless restricted), but the checkbox's own
 * default state matters independently: typo3-pagetree-facets' modal treats
 * ANY checked/non-empty field across ALL tabs as an active filter criterion
 * for its chip bar and "N active filters" toolbar badge - regardless of
 * whether the user ever opens this facet's tab, and regardless of whether any
 * other field in this same tab is set. A checked-by-default checkbox therefore
 * showed up as a permanent, always-active "Include content elements: Enabled"
 * chip on every use of the filter modal, for every facet - confirmed live.
 * Defaulting to unchecked ("pagesOnly" false) means the field contributes
 * nothing until a user deliberately restricts the scope, which is also the
 * one case where the modal's OWN "active criterion" model is correct: the
 * checkbox truly does become one at that point.
 *
 * The marker deliberately contains a space, not a hyphen: TokenSerializer
 * quotes a token's comma-joined value string only when it contains whitespace
 * (or a literal quote) - typo3-pagetree-facets' own toolbar badge then counts
 * a quoted value as a single active criterion instead of comma-splitting it,
 * so the marker does not inflate the "N active filters" count shown outside
 * this facet's own modal (found via a live count mismatch between the tree's
 * toolbar badge and the modal's nav count for this exact reason). A hyphen
 * does not trigger that quoting and was tried first; it produced the mismatch.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentPlannerFacetStateMapper
{
    private const PAGES_ONLY_MARKER = '_pages only';

    /** @var list<string> */
    private const TOKEN_KEYS = ['status', 'assignee', 'comments'];

    /**
     * @param array<string, mixed> $modalState
     *
     * @return list<array{key: string, values: list<string>}>
     */
    public function serialize(array $modalState): array
    {
        // "pagesOnly" absent or empty (unchecked - the modal's own client-side
        // state builder drops a checkbox-group key entirely once nothing in it
        // is checked, same as status/assignee/comments below) means "do not
        // restrict", i.e. content elements stay included - which is both the
        // native HTML-checkbox default AND the desired filtering default, so no
        // special-casing is needed here the way the old, inverted field needed.
        $pagesOnly = in_array('1', $this->listValue($modalState, 'pagesOnly', []), true);
        $includeContentElements = !$pagesOnly;

        $tokens = [];
        foreach (self::TOKEN_KEYS as $key) {
            $values = $this->listValue($modalState, $key, []);
            if ([] === $values) {
                // Intentional: Skipping empty-values keys (not emitting a token for them) is correct.
                // When all three criteria are empty, emitting a token just to preserve the pagesOnly
                // toggle state would cause that token to resolve to zero page uids and zero out the
                // entire AND-intersection in the page tree filter engine, hiding the tree entirely.
                // That is worse than the toggle cosmetically resetting on modal reopen. Since no token
                // is active when all criteria are empty, filtering never happens regardless of what
                // the toggle reads as — the cosmetic reset is an acceptable trade-off.
                continue;
            }
            $tokens[] = ['key' => $key, 'values' => $this->withContentElementsMarker($values, $includeContentElements)];
        }

        return $tokens;
    }

    /**
     * @param list<array{key: string, values: list<string>}> $tokens
     *
     * @return array{status: list<string>, assignee: list<string>, comments: list<string>, pagesOnly: list<string>}
     */
    public function hydrate(array $tokens): array
    {
        $state = ['status' => [], 'assignee' => [], 'comments' => [], 'pagesOnly' => []];

        foreach ($tokens as $token) {
            [$includeContentElements, $values] = $this->extractContentElementsFlag($token['values']);
            if (!$includeContentElements) {
                $state['pagesOnly'] = ['1'];
            }
            if (in_array($token['key'], self::TOKEN_KEYS, true)) {
                $state[$token['key']] = $values;
            }
        }

        return $state;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    public function withContentElementsMarker(array $values, bool $includeContentElements): array
    {
        return $includeContentElements ? $values : array_merge($values, [self::PAGES_ONLY_MARKER]);
    }

    /**
     * @param list<string> $values
     *
     * @return array{0: bool, 1: list<string>}
     */
    public function extractContentElementsFlag(array $values): array
    {
        $includeContentElements = !in_array(self::PAGES_ONLY_MARKER, $values, true);

        return [$includeContentElements, array_values(array_diff($values, [self::PAGES_ONLY_MARKER]))];
    }

    /**
     * @param array<string, mixed> $modalState
     * @param list<string>         $default
     *
     * @return list<string>
     */
    private function listValue(array $modalState, string $field, array $default): array
    {
        $value = $modalState[$field] ?? $default;
        if (!is_array($value)) {
            $value = [$value];
        }

        return array_values(array_map(static fn (mixed $entry): string => (string) $entry, $value));
    }
}
