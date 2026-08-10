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
 * The shared "includeContentElements" modal toggle has no token key of its
 * own - each of the status/assignee/comments tokens is resolved independently
 * by the engine, so the only way for one modal field to influence all three is
 * to bake a sentinel value into every token's own values when content elements
 * are excluded (mirrors Token::FREETEXT's leading-underscore convention for
 * non-criterion values).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentPlannerFacetStateMapper
{
    private const PAGES_ONLY_MARKER = '_pages-only';

    /** @var list<string> */
    private const TOKEN_KEYS = ['status', 'assignee', 'comments'];

    /**
     * @param array<string, mixed> $modalState
     *
     * @return list<array{key: string, values: list<string>}>
     */
    public function serialize(array $modalState): array
    {
        // Default here is [] (unchecked), NOT ['1'] - the modal's own client-side
        // state builder drops a checkbox-group key entirely once nothing in it is
        // checked (the same convention this method already applies to status/
        // assignee/comments below), so "key absent" at serialize() time means "the
        // user unchecked it", not "never touched". The checked-by-default behavior
        // lives in hydrate() below, which runs once when the modal opens and seeds
        // the field's initial value before the user can interact with it at all.
        // Defaulting to ['1'] here instead previously meant every uncheck silently
        // reverted to checked on the very next serialize() call.
        $includeContentElements = in_array('1', $this->listValue($modalState, 'includeContentElements', []), true);

        $tokens = [];
        foreach (self::TOKEN_KEYS as $key) {
            $values = $this->listValue($modalState, $key, []);
            if ([] === $values) {
                // Intentional: Skipping empty-values keys (not emitting a token for them) is correct.
                // When all three criteria are empty, emitting a token just to preserve the
                // includeContentElements toggle state would cause that token to resolve to zero
                // page uids and zero out the entire AND-intersection in the page tree filter engine,
                // hiding the tree entirely. That is worse than the toggle cosmetically resetting
                // on modal reopen. Since no token is active when all criteria are empty, filtering
                // never happens regardless of what the toggle reads as — the cosmetic reset is
                // an acceptable trade-off.
                continue;
            }
            $tokens[] = ['key' => $key, 'values' => $this->withContentElementsMarker($values, $includeContentElements)];
        }

        return $tokens;
    }

    /**
     * @param list<array{key: string, values: list<string>}> $tokens
     *
     * @return array{status: list<string>, assignee: list<string>, comments: list<string>, includeContentElements: list<string>}
     */
    public function hydrate(array $tokens): array
    {
        $state = ['status' => [], 'assignee' => [], 'comments' => [], 'includeContentElements' => ['1']];

        foreach ($tokens as $token) {
            [$includeContentElements, $values] = $this->extractContentElementsFlag($token['values']);
            if (!$includeContentElements) {
                $state['includeContentElements'] = [];
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
