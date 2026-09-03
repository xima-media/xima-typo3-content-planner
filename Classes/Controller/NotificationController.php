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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * NotificationController.
 *
 * AJAX backend for the toolbar notification center (issue #301): the dropdown's list/badge data
 * and the "mark as read" mutations. Every action is scoped to the requesting backend user's own
 * notifications - {@see NotificationRepository::markAsRead()} additionally re-checks ownership at
 * the query level so one user can never mark another user's notification read via a guessed uid.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationController extends ActionController
{
    public function __construct(
        private readonly NotificationCenterDataProvider $dataProvider,
        private readonly NotificationRepository $notificationRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function listAction(ServerRequestInterface $request): JsonResponse
    {
        $backendUserUid = $this->resolveBackendUserUid();
        if (null === $backendUserUid) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $items = $this->dataProvider->getLatestForDropdown($backendUserUid);
        $result = ViewUtility::render('Toolbar/NotificationListFragment.html', ['items' => $items]);

        $unreadCount = $this->dataProvider->getUnreadCount($backendUserUid);

        return new JsonResponse([
            'result' => $result,
            'unreadCount' => $unreadCount,
            'badgeLabel' => $this->dataProvider->getUnreadBadgeLabel($backendUserUid, $unreadCount),
            'pollInterval' => ExtensionUtility::getNotificationPollInterval(),
        ]);
    }

    /**
     * @throws Exception
     */
    public function markReadAction(ServerRequestInterface $request): JsonResponse
    {
        $backendUserUid = $this->resolveBackendUserUid();
        if (null === $backendUserUid) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);
        if ($uid <= 0) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        $this->notificationRepository->markAsRead($uid, $backendUserUid);

        $unreadCount = $this->dataProvider->getUnreadCount($backendUserUid);

        return new JsonResponse([
            'unreadCount' => $unreadCount,
            'badgeLabel' => $this->dataProvider->getUnreadBadgeLabel($backendUserUid, $unreadCount),
        ]);
    }

    /**
     * @throws Exception
     */
    public function markAllReadAction(ServerRequestInterface $request): JsonResponse
    {
        $backendUserUid = $this->resolveBackendUserUid();
        if (null === $backendUserUid) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $this->notificationRepository->markAllAsRead($backendUserUid);

        $unreadCount = $this->dataProvider->getUnreadCount($backendUserUid);

        return new JsonResponse([
            'unreadCount' => $unreadCount,
            'badgeLabel' => $this->dataProvider->getUnreadBadgeLabel($backendUserUid, $unreadCount),
        ]);
    }

    /**
     * Resolves the current backend user's uid, or null when there is none or the extension's
     * content status visibility gate denies them - matching the same gate
     * {@see \Xima\XimaTypo3ContentPlanner\Backend\Toolbar\NotificationToolbarItem::checkAccess()}
     * uses to decide whether to render the toolbar item at all, so a user without it can't reach
     * these actions directly either.
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
