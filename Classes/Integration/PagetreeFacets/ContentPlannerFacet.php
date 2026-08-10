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

use KonradMichalik\PagetreeFacets\Api\{FacetInterface, FilterContext};
use KonradMichalik\PagetreeFacets\Token\Token;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_filter;
use function array_map;
use function array_values;
use function ctype_digit;
use function implode;
use function in_array;

/**
 * ContentPlannerFacet.
 *
 * Thin adapter implementing the real FacetInterface from the suggest-only
 * konradmichalik/typo3-pagetree-facets package. Deliberately kept free of any
 * logic beyond translating Token/FilterContext into the plain types
 * ContentPlannerFacetQuery and ContentPlannerFacetStateMapper expect - see the
 * design spec and Global Constraints in this plan for why this class has no
 * direct unit test and is excluded from PHPStan.
 *
 * @phpstan-ignore class.notFound
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class ContentPlannerFacet implements FacetInterface
{
    public function __construct(
        private ContentPlannerFacetQuery $query,
        private ContentPlannerFacetStateMapper $stateMapper,
        private StatusRepository $statusRepository,
    ) {}

    public function getIdentifier(): string
    {
        return 'contentplanner';
    }

    public function getLabel(): string
    {
        return 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:pagetreeFacets.status';
    }

    public function getGroup(): ?string
    {
        return 'state';
    }

    public function getTokenKeys(): array
    {
        return ['status', 'assignee', 'comments'];
    }

    public function resolvePageUids(Token $token, FilterContext $context): array
    {
        [$includeContentElements, $values] = $this->stateMapper->extractContentElementsFlag($token->values);
        $currentUserUid = (int) ($context->backendUser->user['uid'] ?? 0);

        return match ($token->key) {
            'status' => $this->query->resolveByStatus($this->filterAllowedStatusValues($values), $includeContentElements),
            'assignee' => $this->query->resolveByAssignee($values, $currentUserUid, $includeContentElements),
            'comments' => $this->query->resolveByComments(
                $values,
                $currentUserUid,
                ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS),
                $includeContentElements,
            ),
            default => [],
        };
    }

    public function getModalConfiguration(FilterContext $context): array
    {
        $lll = 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:pagetreeFacets.';

        $statusOptions = [];
        foreach ($this->statusRepository->findAll() as $status) {
            if (!in_array((int) $status->getUid(), $this->allowedStatusUids(), true)) {
                continue;
            }
            $statusOptions[] = [
                'value' => (string) $status->getUid(),
                'label' => $status->getTitle(),
                'icon' => $status->getColoredIcon(),
            ];
        }
        $statusOptions[] = ['value' => 'none', 'label' => $lll.'status.none'];

        $commentsOptions = [
            ['value' => 'open', 'label' => $lll.'comments.open'],
            ['value' => 'resolved', 'label' => $lll.'comments.resolved'],
            ['value' => 'mine', 'label' => $lll.'comments.mine'],
            ['value' => 'none', 'label' => $lll.'comments.none'],
        ];
        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            $commentsOptions[] = ['value' => 'todo', 'label' => $lll.'comments.todo'];
        }

        $currentUser = $context->backendUser->user ?? [];

        return [
            'fields' => [
                [
                    'type' => 'checkbox-group',
                    'name' => 'status',
                    'label' => $lll.'status',
                    'options' => $statusOptions,
                ],
                [
                    'type' => 'user-picker',
                    'name' => 'assignee',
                    'label' => $lll.'assignee',
                    'currentUser' => [
                        'uid' => (int) ($currentUser['uid'] ?? 0),
                        'username' => (string) ($currentUser['username'] ?? ''),
                    ],
                    'pinned' => [
                        ['value' => 'none', 'label' => $lll.'assignee.none'],
                    ],
                ],
                [
                    'type' => 'checkbox-group',
                    'name' => 'comments',
                    'label' => $lll.'comments',
                    'options' => $commentsOptions,
                ],
                [
                    'type' => 'checkbox-group',
                    'name' => 'includeContentElements',
                    'label' => $lll.'includeContentElements',
                    'options' => [
                        ['value' => '1', 'label' => $lll.'includeContentElements.label'],
                    ],
                ],
            ],
        ];
    }

    public function serialize(array $modalState): array
    {
        return array_map(
            static fn (array $entry): Token => new Token(
                $entry['key'],
                $entry['values'],
                $entry['key'].':'.implode(',', $entry['values']),
            ),
            $this->stateMapper->serialize($modalState),
        );
    }

    public function hydrate(array $tokens): array
    {
        return $this->stateMapper->hydrate(array_map(
            static fn (Token $token): array => ['key' => $token->key, 'values' => $token->values],
            $tokens,
        ));
    }

    /**
     * All status uids the current backend user is allowed to see. Computed
     * fresh per call (StatusRepository/PermissionUtility already memoize their
     * own lookups internally, see [[assert-against-reality-not-your-own-constant]]
     * for why this isn't re-cached here on top of that).
     *
     * @return list<int>
     */
    private function allowedStatusUids(): array
    {
        $uids = [];
        foreach ($this->statusRepository->findAll() as $status) {
            $uids[] = (int) $status->getUid();
        }

        return array_values(array_filter($uids, PermissionUtility::isStatusAllowedForUser(...)));
    }

    /**
     * Filters a status token's raw values down to uids the current user may
     * see (via the pure, unit-tested ContentPlannerFacetQuery::filterAllowedStatusUids())
     * before handing them to the query layer - a disallowed uid must resolve to
     * no match, not an error, and never reveal whether the status exists.
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function filterAllowedStatusValues(array $values): array
    {
        $matchNone = in_array('none', $values, true);
        $requestedUids = array_values(array_filter(array_map(
            static fn (string $value): ?int => ctype_digit($value) ? (int) $value : null,
            $values,
        ), static fn (?int $value): bool => null !== $value));

        $filteredUids = $this->query->filterAllowedStatusUids($requestedUids, $this->allowedStatusUids());
        $filteredValues = array_map(static fn (int $uid): string => (string) $uid, $filteredUids);
        if ($matchNone) {
            $filteredValues[] = 'none';
        }

        return $filteredValues;
    }
}
