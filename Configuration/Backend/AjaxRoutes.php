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
    'ximatypo3contentplanner_filterrecords' => [
        'path' => '/content-planner/records',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::filterAction',
    ],
    'ximatypo3contentplanner_comments' => [
        'path' => '/content-planner/comments',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::commentsAction',
    ],
    'ximatypo3contentplanner_assignees' => [
        'path' => '/content-planner/assignees',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::assigneeSelectionAction',
    ],
    'ximatypo3contentplanner_message' => [
        'path' => '/content-planner/message',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class.'::messageAction',
    ],
    'ximatypo3contentplanner_usersetting' => [
        'path' => '/content-planner/user-setting',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\RecordController::class.'::userSettingAction',
    ],
    'ximatypo3contentplanner_closedocument' => [
        'path' => '/content-planner/close-document',
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ProxyController::class.'::closeDocumentAction',
    ],
];

// The frontend API endpoints are not registered at all while the feature flag is off, so
// no guard is needed in the actions themselves. Verified in a 13.4 backend: an
// unregistered backend route is answered with a redirect to the backend shell, i.e. a
// consumer sees a 200 with an HTML document rather than a JSON error — the same shape it
// already gets when a session expires, so it has to check the content type either way.
// The flag only governs availability: these stay backend routes requiring a backend
// session and a CSRF route token.
if (Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility::isFrontendApiEnabled()) {
    $routes['ximatypo3contentplanner_api_summary'] = [
        'path' => '/content-planner/api/summary',
        'methods' => ['POST'],
        'target' => Xima\XimaTypo3ContentPlanner\Controller\ApiController::class.'::summaryAction',
    ];
}

return $routes;
