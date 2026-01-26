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

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Psr\Http\Message\{ServerRequestInterface, UriInterface};
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;

use function is_int;

/**
 * SelectionUriBuilder.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class SelectionUriBuilder
{
    public function __construct(
        private readonly UriBuilder $uriBuilder,
    ) {}

    /**
     * @param array<int, int>|int $uid
     *
     * @throws RouteNotFoundException
     */
    public function buildUriForStatusChange(string $table, array|int $uid, ?Status $status): UriInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $route = $request->getAttribute('routing')->getRoute()->getOption('_identifier');

        $routeArray = $this->buildRouteArrayForRoute($route, $table, $uid, $request);
        $dataArray = $this->buildDataArrayForStatusChange($table, $uid, $status);

        return $this->uriBuilder->buildUriFromRoute(
            'tce_db',
            [
                'data' => $dataArray,
                'redirect' => $this->uriBuilder->buildUriFromRoute(
                    'ximatypo3contentplanner_message',
                    [
                        'redirect' => (string) $this->uriBuilder->buildUriFromRoute($route, $routeArray),
                        'message' => $status instanceof Status ? 'status.changed' : 'status.reset',
                    ],
                ),
            ],
        );
    }

    /**
     * @throws RouteNotFoundException
     */
    public function buildUriForFolderStatusChange(string $combinedIdentifier, ?Status $status): UriInterface
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $currentFolderId = $request->getQueryParams()['id'] ?? '';

        return $this->uriBuilder->buildUriFromRoute(
            'ximatypo3contentplanner_folder_status_update',
            [
                'identifier' => $combinedIdentifier,
                'status' => $status instanceof Status ? $status->getUid() : 0,
                'redirect' => (string) $this->uriBuilder->buildUriFromRoute(
                    'ximatypo3contentplanner_message',
                    [
                        'redirect' => (string) $this->uriBuilder->buildUriFromRoute('media_management', ['id' => $currentFolderId]),
                        'message' => $status instanceof Status ? 'status.changed' : 'status.reset',
                    ],
                ),
            ],
        );
    }

    /**
     * @param array<int, int>|int $uid
     *
     * @return array<string, mixed>
     */
    private function buildRouteArrayForRoute(string $route, string $table, array|int $uid, ServerRequestInterface $request): array
    {
        if ('record_edit' === $route) {
            return [
                'edit' => [
                    $table => [
                        $uid => 'edit',
                    ],
                ],
            ];
        }

        if (RouteUtility::isRecordListRoute($route)) {
            $currentPageId = (int) ($request->getQueryParams()['id'] ?? 0);

            return [
                'id' => $currentPageId ?: $uid,
            ];
        }

        if (RouteUtility::isFileListRoute($route)) {
            return [
                'id' => $request->getQueryParams()['id'] ?? '',
            ];
        }

        return [
            'id' => $uid,
        ];
    }

    /**
     * @param array<int, int>|int $uid
     *
     * @return array<string, array<int, array<string, int|string>>>
     */
    private function buildDataArrayForStatusChange(string $table, array|int $uid, ?Status $status): array
    {
        $dataArray = [
            $table => [],
        ];

        if (is_int($uid)) {
            $uid = [$uid];
        }

        foreach ($uid as $singleId) {
            $dataArray[$table][$singleId] = [
                Configuration::FIELD_STATUS => $status instanceof Status ? $status->getUid() : '',
            ];
        }

        return $dataArray;
    }
}
