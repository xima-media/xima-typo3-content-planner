<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Core\Http\{JsonResponse, RedirectResponse};
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\FolderStatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function is_array;

/**
 * FolderController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class FolderController extends ActionController
{
    public function __construct(
        private readonly FolderStatusRepository $folderStatusRepository,
    ) {}

    /**
     * Update or create folder status.
     *
     * @throws Exception
     */
    public function updateStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!ExtensionUtility::isFilelistSupportEnabled()) {
            return new JsonResponse(['error' => 'Filelist support is not enabled'], 403);
        }

        // Support both GET (from links) and POST (from AJAX) requests
        $body = $request->getParsedBody() ?? [];
        $query = $request->getQueryParams();
        $identifier = $body['identifier'] ?? $query['identifier'] ?? null;
        $statusValue = $body['status'] ?? $query['status'] ?? null;
        $status = null !== $statusValue ? (int) $statusValue : null;
        $assignee = is_array($body) && isset($body['assignee']) ? (int) $body['assignee'] : null;
        $redirect = $query['redirect'] ?? null;

        if (null === $identifier || '' === $identifier) {
            return new JsonResponse(['error' => 'Missing folder identifier'], 400);
        }

        // Convert status 0 to null (reset)
        if (0 === $status) {
            $status = null;
        }

        try {
            $uid = $this->folderStatusRepository->createOrUpdate($identifier, $status, $assignee);

            // If redirect URL is provided, redirect instead of returning JSON
            if (null !== $redirect && '' !== $redirect) {
                return new RedirectResponse($redirect);
            }

            return new JsonResponse([
                'success' => true,
                'uid' => $uid,
                'identifier' => $identifier,
            ]);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
