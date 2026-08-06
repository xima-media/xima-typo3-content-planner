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

namespace Xima\XimaTypo3ContentPlanner\Service;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\MessageResult;

/**
 * ResultMessageService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ResultMessageService
{
    /**
     * Single source for the messages of status, assignee and comment actions, so a
     * backend flash message and a JSON response cannot drift apart.
     *
     * Uses self::LANGUAGE_FILE, which the code style orders below this constant —
     * harmless, as PHP resolves constant expressions on first access, not in
     * declaration order.
     */
    public const MESSAGES = [
        'status' => [
            'changed' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.status.changed.success.title',
                    'message' => self::LANGUAGE_FILE.'message.status.changed.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.status.changed.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.status.changed.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'reset' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.status.reset.success.title',
                    'message' => self::LANGUAGE_FILE.'message.status.reset.success.message',
                    'severity' => ContextualFeedbackSeverity::NOTICE,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.status.reset.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.status.reset.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
        ],
        'assignee' => [
            'changed' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.assignee.changed.success.title',
                    'message' => self::LANGUAGE_FILE.'message.assignee.changed.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.assignee.changed.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.assignee.changed.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'reset' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.assignee.reset.success.title',
                    'message' => self::LANGUAGE_FILE.'message.assignee.reset.success.message',
                    'severity' => ContextualFeedbackSeverity::NOTICE,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.assignee.reset.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.assignee.reset.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
        ],
        'comment' => [
            'create' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.create.success.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.create.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.create.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.create.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'edit' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.edit.success.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.edit.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.edit.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.edit.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'resolve' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.resolve.success.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.resolve.success.message',
                    'severity' => ContextualFeedbackSeverity::OK,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.resolve.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.resolve.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
            'delete' => [
                'success' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.delete.success.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.delete.success.message',
                    'severity' => ContextualFeedbackSeverity::WARNING,
                ],
                'failure' => [
                    'title' => self::LANGUAGE_FILE.'message.comment.delete.failure.title',
                    'message' => self::LANGUAGE_FILE.'message.comment.delete.failure.message',
                    'severity' => ContextualFeedbackSeverity::ERROR,
                ],
            ],
        ],
    ];
    private const LANGUAGE_FILE = 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:';

    /**
     * Resolves a dot-separated message path such as `status.changed` into a localized
     * result, or null if the path or result status is unknown.
     *
     * Needs neither a request nor a redirect, so the JSON endpoints and the redirect
     * chain can share the exact same wording and severity.
     *
     * $resultStatus selects the wording variant. It is not echoed back into the result —
     * an endpoint that determines an outcome reports that itself.
     */
    public function resolve(string $messagePath, string $resultStatus = 'success'): ?MessageResult
    {
        $message = $this->lookup($messagePath, $resultStatus);
        if (null === $message) {
            return null;
        }

        return new MessageResult(
            title: $this->getLanguageService()->sL($message['title']),
            message: $this->getLanguageService()->sL($message['message']),
            severity: $message['severity'],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return array{title: string, message: string, severity: ContextualFeedbackSeverity}|null
     */
    private function lookup(string $messagePath, string $resultStatus): ?array
    {
        $keys = explode('.', $messagePath);
        $keys[] = $resultStatus;
        $messages = self::MESSAGES;

        foreach ($keys as $key) {
            if (!isset($messages[$key])) {
                return null;
            }
            $messages = $messages[$key];
        }

        // A path shorter than the catalogue depth lands on a nested array rather than a
        // leaf, e.g. `status` resolving to the whole `changed`/`reset` branch.
        if (!isset($messages['title'], $messages['message'], $messages['severity'])) {
            return null;
        }

        return $messages;
    }
}
