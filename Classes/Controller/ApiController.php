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

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary\RecordSummary;
use Xima\XimaTypo3ContentPlanner\Service\RecordSummaryService;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_map;
use function count;
use function is_array;
use function is_string;

/**
 * ApiController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ApiController
{
    /**
     * Guards against a consumer asking for an unbounded number of records in one call.
     * A page's worth of content elements stays far below this.
     */
    private const MAX_ITEMS = 500;

    public function __construct(private readonly RecordSummaryService $recordSummaryService) {}

    /**
     * Returns status, assignee and comment counters for many records in one request.
     *
     * The route is only registered while the frontend API is enabled, so reaching this
     * action already implies the feature flag, a backend session and a valid route token.
     */
    public function summaryAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Content planner is not visible for this user'], 403);
        }

        $items = $this->parseItems($request);
        if (null === $items) {
            return new JsonResponse(['error' => 'Expected a JSON body of shape {"items":[{"table":"…","uid":1}]}'], 400);
        }

        if (count($items) > self::MAX_ITEMS) {
            return new JsonResponse(['error' => 'Too many items, at most '.self::MAX_ITEMS.' per request'], 400);
        }

        $summaries = $this->recordSummaryService->buildForItems($items);

        return new JsonResponse([
            'items' => array_map(static fn (RecordSummary $summary): array => $summary->toArray(), $summaries),
        ]);
    }

    /**
     * @return array<int, array{table: string, uid: int}>|null null when the payload is unusable
     */
    private function parseItems(ServerRequestInterface $request): ?array
    {
        $body = $request->getParsedBody();

        // Backend AJAX routes only decode form-encoded bodies, so a JSON content type
        // arrives as a raw stream and has to be decoded here.
        if (!is_array($body)) {
            $decoded = json_decode((string) $request->getBody(), true);
            $body = is_array($decoded) ? $decoded : null;
        }

        if (null === $body || !isset($body['items']) || !is_array($body['items'])) {
            return null;
        }

        $items = [];
        foreach ($body['items'] as $item) {
            if (!is_array($item) || !isset($item['table'], $item['uid']) || !is_string($item['table'])) {
                continue;
            }

            $items[] = ['table' => $item['table'], 'uid' => (int) $item['uid']];
        }

        return $items;
    }
}
