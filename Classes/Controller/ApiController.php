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
use Xima\XimaTypo3ContentPlanner\Service\{RecordSummaryService, ResultMessageService, StatusChangeApiService, StatusSelectionApiService};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
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

    public function __construct(
        private readonly RecordSummaryService $recordSummaryService,
        private readonly StatusSelectionApiService $statusSelectionApiService,
        private readonly StatusChangeApiService $statusChangeApiService,
        private readonly ResultMessageService $resultMessageService,
    ) {}

    /**
     * Applies a status change through the DataHandler, so the result is indistinguishable
     * from one made in the backend UI, and answers with the message the backend would
     * show plus the record's fresh summary.
     */
    public function statusAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Content planner is not visible for this user'], 403);
        }

        $body = $this->readBody($request);
        // "status" has to be present: a missing key is a malformed request, not a reset.
        if (null === $body || !is_string($body['table'] ?? null) || !array_key_exists('status', $body)) {
            return new JsonResponse(['error' => 'Expected a JSON body of shape {"table":"…","uid":1,"status":2|null}'], 400);
        }

        $table = $body['table'];
        $uid = (int) ($body['uid'] ?? 0);
        if ('' === $table || $uid <= 0) {
            return new JsonResponse(['error' => 'Expected a JSON body of shape {"table":"…","uid":1,"status":2|null}'], 400);
        }

        $requestedStatus = null === $body['status'] ? null : (int) $body['status'];
        $result = $this->statusChangeApiService->apply($table, $uid, $requestedStatus);

        return match ($result['outcome']) {
            StatusChangeApiService::OUTCOME_UNKNOWN_RECORD,
            StatusChangeApiService::OUTCOME_TABLE_NOT_ALLOWED => new JsonResponse(
                ['error' => 'Record not found or not accessible'],
                404,
            ),
            StatusChangeApiService::OUTCOME_STRIPPED => $this->envelope($requestedStatus, 'failure', null, 403),
            default => $this->envelope($requestedStatus, 'success', $this->summaryFor($table, $uid), 200),
        };
    }

    /**
     * Returns the statuses selectable for one record, filtered exactly as the backend
     * menus are, because the same PrepareStatusSelectionEvent runs.
     */
    public function statusSelectionAction(ServerRequestInterface $request): ResponseInterface
    {
        $table = (string) ($request->getQueryParams()['table'] ?? '');
        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);

        if ('' === $table || $uid <= 0) {
            return new JsonResponse(['error' => 'Both a table and a uid are required'], 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Content planner is not visible for this user'], 403);
        }

        $selection = $this->statusSelectionApiService->buildForRecord($table, $uid);
        if (null === $selection) {
            return new JsonResponse(['error' => 'Record not found or not accessible'], 404);
        }

        return new JsonResponse($selection->toArray());
    }

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

        $rawItems = $this->readItemList($request);
        if (null === $rawItems) {
            return new JsonResponse(['error' => 'Expected a JSON body of shape {"items":[{"table":"…","uid":1}]}'], 400);
        }

        // Counted before normalizing: malformed entries are dropped there, so counting
        // afterwards would let an oversized batch of them slip through and answer 200
        // with an empty list instead of rejecting the request.
        if (count($rawItems) > self::MAX_ITEMS) {
            return new JsonResponse(['error' => 'Too many items, at most '.self::MAX_ITEMS.' per request'], 400);
        }

        $summaries = $this->recordSummaryService->buildForItems($this->normalizeItems($rawItems));

        return new JsonResponse([
            'items' => array_map(static fn (RecordSummary $summary): array => $summary->toArray(), $summaries),
        ]);
    }

    /**
     * Builds the response for a status change: the message the backend would show, and on
     * success the record's fresh summary so a consumer can re-render without a second call.
     *
     * `success` is supplied here rather than by MessageResult, because this is the layer
     * that performed the write and therefore knows the outcome.
     *
     * @param array<string, mixed>|null $summary
     */
    private function envelope(?int $requestedStatus, string $resultStatus, ?array $summary, int $httpStatus): JsonResponse
    {
        $result = $this->resultMessageService->resolve(
            null === $requestedStatus ? 'status.reset' : 'status.changed',
            $resultStatus,
        );

        $payload = ['success' => 'success' === $resultStatus];
        if (null !== $result) {
            $payload += $result->toArray();
        }
        if (null !== $summary) {
            $payload['record'] = $summary;
        }

        return new JsonResponse($payload, $httpStatus);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summaryFor(string $table, int $uid): ?array
    {
        $summaries = $this->recordSummaryService->buildForItems([['table' => $table, 'uid' => $uid]]);

        return [] === $summaries ? null : $summaries[0]->toArray();
    }

    /**
     * @return array<string, mixed>|null the decoded JSON body, or null when unusable
     */
    private function readBody(ServerRequestInterface $request): ?array
    {
        $body = $request->getParsedBody();

        // Backend AJAX routes only decode form-encoded bodies, so a JSON content type
        // arrives as a raw stream and has to be decoded here.
        if (!is_array($body)) {
            $decoded = json_decode((string) $request->getBody(), true);
            $body = is_array($decoded) ? $decoded : null;
        }

        return $body;
    }

    /**
     * @return array<int, mixed>|null the raw item list, or null when the payload is unusable
     */
    private function readItemList(ServerRequestInterface $request): ?array
    {
        $body = $this->readBody($request);

        if (null === $body || !isset($body['items']) || !is_array($body['items'])) {
            return null;
        }

        return array_values($body['items']);
    }

    /**
     * Drops entries that cannot address a record. Silent, because a consumer batching a
     * page's elements should not lose the whole response over one bad entry.
     *
     * @param array<int, mixed> $rawItems
     *
     * @return array<int, array{table: string, uid: int}>
     */
    private function normalizeItems(array $rawItems): array
    {
        $items = [];
        foreach ($rawItems as $item) {
            if (!is_array($item) || !isset($item['table'], $item['uid']) || !is_string($item['table'])) {
                continue;
            }

            $items[] = ['table' => $item['table'], 'uid' => (int) $item['uid']];
        }

        return $items;
    }
}
