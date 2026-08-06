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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * MessageResult.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class MessageResult
{
    public function __construct(
        public string $title,
        public string $message,
        public ContextualFeedbackSeverity $severity,
    ) {}

    /**
     * The localized message as it goes over the wire. Deliberately carries no success
     * flag: whether an action worked is known by whoever performed it, not by the message
     * catalogue, so an endpoint that writes prepends its own outcome. Deriving it from the
     * severity would be wrong too — a successful comment deletion is a WARNING and a
     * status reset is a NOTICE.
     *
     * No queue, request or rendering concern is involved, so the same result can just as
     * well be enqueued as a flash message.
     *
     * @return array{title: string, message: string, severity: int}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            // Consumers switch on the numeric severity, so the enum is unwrapped here
            // rather than left to json_encode().
            'severity' => $this->severity->value,
        ];
    }
}
