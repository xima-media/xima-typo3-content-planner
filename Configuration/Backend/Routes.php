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

$routes = [
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'access' => 'public',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class.'::messageAction',
    ],
    'ximatypo3contentplanner_folder_status_update' => [
        'path' => '/content-planner/folder/status/update',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\FolderController::class.'::updateStatusAction',
    ],
    'ximatypo3contentplanner_share' => [
        'path' => '/content-planner/share',
        'access' => 'public',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::shareAction',
    ],
];

// Not registered at all while the feature flag is off, so no guard is needed in the action
// itself. Note that an unregistered backend route is answered with a redirect to the
// backend shell rather than a 404, so a consumer cannot tell "disabled" from "session
// expired" by status code alone.
if (Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::isEmbeddableCommentsViewEnabled()) {
    $routes['ximatypo3contentplanner_comments_view'] = [
        'path' => Xima\XimaTypo3ContentPlanner\Configuration::ROUTE_PATH_COMMENTS_VIEW,
        'target' => Xima\XimaTypo3ContentPlanner\Controller\CommentsViewController::class.'::indexAction',
    ];
}

return $routes;
