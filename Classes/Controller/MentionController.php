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

use Doctrine\DBAL\Exception;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_string;
use function str_contains;

/**
 * MentionController.
 *
 * AJAX backend for the @-mention suggestion feed (issue #305): a pragmatic approximation of "BE
 * users with access to the record" - every content-planner-permitted user
 * ({@see BackendUserRepository::findAllWithPermission()}), the same pool
 * {@see RecordController::assigneeSelectionAction()} already offers for assignment - rather than
 * exact per-record permission resolution (groups/mounts/page permissions), which the issue
 * explicitly defers as too expensive to evaluate for every keystroke of a live suggestion feed.
 *
 * Response contract for {@see self::suggestAction()} (`{result: list<{id, uid, name}>}`) is the
 * one a future CKEditor5 comment composer (issue #327, a separate branch stack - see this
 * issue's PR description) needs to wire into its Mention plugin's `feed` callback: `id` is what
 * CKEditor5's Mention plugin itself requires (a string beginning with the marker character),
 * `uid`/`name` are this extension's own additions for building the persisted marker
 * ({@see \Xima\XimaTypo3ContentPlanner\Utility\Data\MentionUtility}) and the visible label.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class MentionController extends ActionController
{
    /**
     * Caps one suggestion response, matching typical mention-feed UX (a short, scrollable
     * dropdown) and avoiding turning this endpoint into a full user-directory listing.
     */
    private const MAX_SUGGESTIONS = 10;

    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly BackendUserRepository $backendUserRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function suggestAction(ServerRequestInterface $request): ResponseInterface
    {
        $table = (string) ($request->getQueryParams()['table'] ?? '');
        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);
        $term = (string) ($request->getQueryParams()['term'] ?? '');

        if ('' === $table || 0 === $uid) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility() || !PermissionUtility::canCreateComment()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->recordRepository->findByUid($table, $uid, true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return new JsonResponse(['result' => $this->buildSuggestions($term)]);
    }

    /**
     * @return list<array{id: string, uid: int, name: string}>
     */
    private function buildSuggestions(string $term): array
    {
        $term = mb_strtolower(trim($term));
        $suggestions = [];

        foreach ($this->backendUserRepository->findAllWithPermission() as $user) {
            if ('' !== $term && !$this->matchesTerm($user, $term)) {
                continue;
            }

            $suggestions[] = [
                'id' => '@'.$user['username'],
                'uid' => (int) $user['uid'],
                'name' => ContentUtility::generateDisplayName($user),
            ];

            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function matchesTerm(array $user, string $term): bool
    {
        $username = is_string($user['username'] ?? null) ? $user['username'] : '';
        $realName = is_string($user['realName'] ?? null) ? $user['realName'] : '';

        return str_contains(mb_strtolower($username.' '.$realName), $term);
    }
}
