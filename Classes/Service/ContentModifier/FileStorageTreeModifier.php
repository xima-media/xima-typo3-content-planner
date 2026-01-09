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

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Doctrine\DBAL\Exception;
use JsonException;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Configuration\Colors;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function in_array;
use function is_array;

/**
 * FileStorageTreeModifier.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class FileStorageTreeModifier implements ModifierInterface
{
    /**
     * AJAX route identifiers for the FileStorageTree.
     * Note: filestorage_tree_rootline is excluded as it returns a different structure.
     */
    private const TREE_ROUTES = [
        'ajax_filestorage_tree_data',
        'ajax_filestorage_tree_filter',
    ];

    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly FolderStatusRepository $folderStatusRepository,
    ) {}

    public function isRelevant(ServerRequestInterface $request): bool
    {
        // Only for v13 (v14 uses native event)
        if (VersionUtility::is14OrHigher()) {
            return false;
        }

        if (!PermissionUtility::checkContentStatusVisibility() || !ExtensionUtility::isFilelistSupportEnabled()) {
            return false;
        }

        // Check if this is a FileStorageTree AJAX route
        $route = $request->getAttribute('route');
        if (null === $route) {
            return false;
        }

        $routeIdentifier = $route->getOption('_identifier');

        return in_array($routeIdentifier, self::TREE_ROUTES, true);
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Get response body
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();

        if ('' === $content) {
            return $response;
        }

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return $response;
        }

        // Process tree items
        $this->processTreeItems($data);

        return new JsonResponse($data);
    }

    /**
     * Recursively process tree items and add status labels to folders.
     *
     * @param array<int|string, mixed> $items
     *
     * @throws Exception
     */
    private function processTreeItems(array &$items): void
    {
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }

            // Check if this is a folder item
            if (isset($item['resourceType']) && 'folder' === $item['resourceType']) {
                $this->addStatusLabelToFolder($item);
            }
        }
    }

    /**
     * Add status label to a folder item if it has a status assigned.
     *
     * @param array<string, mixed> $item
     *
     * @throws Exception
     */
    private function addStatusLabelToFolder(array &$item): void
    {
        // Build combined identifier from storage and pathIdentifier
        if (!isset($item['storage'], $item['pathIdentifier'])) {
            return;
        }

        $combinedIdentifier = (int) $item['storage'].':'.urldecode($item['pathIdentifier']);
        $folderStatus = $this->folderStatusRepository->findByCombinedIdentifier($combinedIdentifier);

        // Initialize labels array if not exists
        if (!isset($item['labels']) || !is_array($item['labels'])) {
            $item['labels'] = [];
        }

        if (false === $folderStatus || !isset($folderStatus[Configuration::FIELD_STATUS]) || 0 === (int) $folderStatus[Configuration::FIELD_STATUS]) {
            // Add empty label to prevent inheritance from parent folders
            $item['labels'][] = [
                'label' => '',
                'color' => 'inherit',
                'priority' => 0,
            ];

            return;
        }

        $status = $this->statusRepository->findByUid((int) $folderStatus[Configuration::FIELD_STATUS]);

        if (!$status instanceof Status) {
            return;
        }

        $item['labels'][] = [
            'label' => $status->getTitle(),
            'color' => Colors::getHex($status->getColor()),
            'priority' => 0,
        ];
    }
}
