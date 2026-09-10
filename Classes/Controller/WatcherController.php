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
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Service\{WatcherPresentationService, WatcherService};
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * WatcherController.
 *
 * AJAX backend for the watch/unwatch toggle UI (issue #303): a single toggle endpoint flips the
 * requesting backend user's watcher relation for one record and returns the updated
 * {@see WatcherPresentationService} read model so the button can update itself without a reload.
 *
 * Toggle direction is derived from {@see WatcherService::isWatching()}, not the mode the client
 * last saw: a currently-active watcher (whether {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\WatchMode::Auto}
 * or {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\WatchMode::ManualWatch}) always mutes on
 * click, and anyone not currently watching (never watched, or previously muted) always starts an
 * explicit manual watch - see the class docblock of {@see WatcherService} for why muting is
 * sticky against every future auto-watch trigger.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class WatcherController extends ActionController
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly WatcherService $watcherService,
        private readonly WatcherPresentationService $watcherPresentationService,
    ) {}

    /**
     * @throws Exception
     */
    public function toggleAction(ServerRequestInterface $request): ResponseInterface
    {
        $table = (string) ($request->getQueryParams()['table'] ?? '');
        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);

        if ('' === $table || 0 === $uid) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        $backendUserUid = $this->resolveBackendUserUid();
        if (null === $backendUserUid) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        if (!$this->watcherService->isWatchable($table)) {
            return new JsonResponse(['error' => 'Table does not support watching'], 400);
        }

        $record = $this->recordRepository->findByUid($table, $uid, true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        if ($this->watcherService->isWatching($table, $uid, $backendUserUid)) {
            $this->watcherService->unwatch($table, $uid, $backendUserUid);
        } else {
            $this->watcherService->watch($table, $uid, $backendUserUid, WatchSource::Manual);
        }

        $state = $this->watcherPresentationService->build($table, $uid, $backendUserUid);

        // "result" carries a server-rendered replacement for the toggle button group (same
        // Partials/WatchToggle.html the initial banner render uses via InfoGenerator), so the
        // client only ever needs to swap markup - the icon/color/badge-per-mode mapping lives in
        // exactly one place instead of being duplicated in JavaScript.
        $state['result'] = ViewUtility::render('Default/WatchToggle.html', [
            'table' => $table,
            'uid' => $uid,
            'watch' => $state,
        ]);

        return new JsonResponse($state);
    }

    /**
     * Resolves the current backend user's uid, or null when there is none or the extension's
     * content status visibility gate denies them - the same gate the rest of the extension's
     * banner UI (assignee/comments) is already rendered behind.
     */
    private function resolveBackendUserUid(): ?int
    {
        $backendUserUid = PermissionUtility::getCurrentUserId();
        if (null === $backendUserUid || !PermissionUtility::checkContentStatusVisibility()) {
            return null;
        }

        return $backendUserUid;
    }
}
