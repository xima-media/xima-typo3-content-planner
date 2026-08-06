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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\{JsonResponse, RedirectResponse};
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageQueue, FlashMessageService};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\MessageResult;
use Xima\XimaTypo3ContentPlanner\Service\ResultMessageService;

use function count;
use function is_array;

/**
 * ProxyController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ProxyController extends ActionController
{
    /**
     * Kept so existing consumers reading the catalogue keep working.
     *
     * @deprecated use ResultMessageService::MESSAGES instead
     */
    public const MESSAGES = ResultMessageService::MESSAGES;

    public function __construct(
        private readonly FlashMessageService $flashMessageService,
        private readonly ResultMessageService $resultMessageService,
    ) {}

    public function messageAction(ServerRequestInterface $request): ResponseInterface
    {
        $messagePath = $request->getQueryParams()['message'] ?? null;
        if (null === $messagePath) {
            return new JsonResponse(['error' => 'Missing message parameter'], 400);
        }

        $redirect = $request->getQueryParams()['redirect'] ?? null;
        if (null !== $redirect) {
            $redirect = GeneralUtility::sanitizeLocalUrl($redirect);
            if ('' === $redirect) {
                return new JsonResponse(['error' => 'Invalid redirect target'], 400);
            }

            $result = $this->resultMessageService->resolve($messagePath);
            if (null === $result) {
                return new JsonResponse(['error' => 'Invalid message path'], 400);
            }

            $this->enqueueNotification($result);

            return new RedirectResponse($redirect);
        }

        $resultStatus = $request->getQueryParams()['resultStatus'] ?? 'success';
        $result = $this->resultMessageService->resolve($messagePath, $resultStatus);
        if (null === $result) {
            return new JsonResponse(['error' => 'Invalid message path or result status'], 400);
        }

        return new JsonResponse($result->toArray());
    }

    /**
     * Removes orphaned "open document" references of the comment table from the FormEngine
     * module data. Comments are edited through an ephemeral iframe modal: after saving, FormEngine
     * re-renders the record in edit mode and registers it as an open document, which the modal then
     * silently closes — leaving a stale entry in the "opendocs" toolbar. This cleans it up.
     */
    public function closeDocumentAction(ServerRequestInterface $request): ResponseInterface
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $docData = $backendUser->getModuleData('FormEngine');

        if (!is_array($docData) || !isset($docData[0]) || !is_array($docData[0])) {
            return new JsonResponse(['openDocuments' => 0, 'removed' => 0]);
        }

        $docHandler = $docData[0];
        $removed = 0;
        foreach ($docHandler as $identifier => $document) {
            if (Configuration::TABLE_COMMENT === ($document[3]['table'] ?? null)) {
                unset($docHandler[$identifier]);
                ++$removed;
            }
        }

        if ($removed > 0) {
            $backendUser->pushModuleData('FormEngine', [$docHandler, $docData[1] ?? '']);
        }

        return new JsonResponse(['openDocuments' => count($docHandler), 'removed' => $removed]);
    }

    private function enqueueNotification(MessageResult $result): void
    {
        $this->flashMessageService->getMessageQueueByIdentifier(
            FlashMessageQueue::NOTIFICATION_QUEUE,
        )->addMessage(
            GeneralUtility::makeInstance(
                FlashMessage::class,
                $result->message,
                $result->title,
                $result->severity,
                true,
            ),
        );
    }
}
