<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;

class ProxyController extends ActionController
{
    public const MESSAGES = [
        'status' => [
            'changed' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.changed.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.changed.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.changed.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.changed.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'reset' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.reset.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.reset.success.message',
                    'severity' => ContextualFeedbackSeverity::NOTICE,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.reset.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.status.reset.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
        ],
        'assignee' => [
            'changed' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.changed.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.changed.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.changed.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.changed.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'reset' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.reset.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.reset.success.message',
                    'severity' => ContextualFeedbackSeverity::NOTICE,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.reset.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.assignee.reset.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
        ],
        'comment' => [
            'create' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.create.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.create.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.create.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.create.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'edit' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.edit.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.edit.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.edit.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.edit.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'resolve' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.resolve.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.resolve.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.resolve.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.resolve.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'delete' => [
                'success' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.delete.success.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.delete.success.message',
                    'severity' => ContextualFeedbackSeverity::WARNING,
                ],
                'failure' => [
                    'title' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.delete.failure.title',
                    'message' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:message.comment.delete.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
        ],
    ];

    public function messageAction(ServerRequestInterface $request): ResponseInterface
    {
        $messagePath = $request->getQueryParams()['message'] ?? null;
        if ($messagePath === null) {
            return new JsonResponse(['error' => 'Missing message parameter'], 400);
        }

        $redirect = $request->getQueryParams()['redirect'] ?? null;
        if ($redirect !== null) {
            $message = $this->getMessageByDotNotation($messagePath);
            if ($message === null) {
                return new JsonResponse(['error' => 'Invalid message path'], 400);
            }

            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $notificationQueue = $flashMessageService->getMessageQueueByIdentifier(
                FlashMessageQueue::NOTIFICATION_QUEUE
            );
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->getLanguageService()->sL($message['message']),
                $this->getLanguageService()->sL($message['title']),
                $message['severity'],
                true
            );
            $notificationQueue->addMessage($flashMessage);

            return new RedirectResponse($redirect);
        }

        $resultStatus = $request->getQueryParams()['resultStatus'] ?? 'success';
        $message = $this->getMessageByDotNotation($messagePath, $resultStatus);
        if ($message === null) {
            return new JsonResponse(['error' => 'Invalid message path or result status'], 400);
        }

        return new JsonResponse([
            'title' => $this->getLanguageService()->sL($message['title']),
            'message' => $this->getLanguageService()->sL($message['message']),
            'severity' => $message['severity'],
        ]);
    }

    /**
    * @return array<string, mixed>|null
    */
    protected function getMessageByDotNotation(string $dotNotation, string $resultStatus = 'success'): ?array
    {
        $keys = explode('.', $dotNotation);
        $keys[] = $resultStatus;
        $messages = self::MESSAGES;

        foreach ($keys as $key) {
            if (!isset($messages[$key])) {
                return null;
            }
            $messages = $messages[$key];
        }
        return $messages;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
