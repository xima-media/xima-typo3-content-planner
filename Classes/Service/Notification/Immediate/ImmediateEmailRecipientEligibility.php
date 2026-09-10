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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate;

use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function array_key_exists;
use function is_string;

/**
 * ImmediateEmailRecipientEligibility.
 *
 * The immediate-email channel's eligibility policy (issue #306): the feature flag, the
 * recipient's enabled state, both opt-in toggles, and a syntactically valid email address. Shared
 * between {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\ImmediateEmailChannel},
 * which applies it when a notification first arrives, and {@see ImmediateEmailService}, which must
 * reapply the exact same policy in {@see ImmediateEmailService::flushDueQueues()}'s scheduled path
 * - eligibility can have changed between a row being queued and the scheduled flush picking it up.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
readonly class ImmediateEmailRecipientEligibility
{
    /**
     * @param array<string, mixed> $recipient
     */
    public function isEligible(array $recipient): bool
    {
        if (!ExtensionUtility::isNotificationImmediateEmailEnabled()) {
            return false;
        }

        if ((bool) ($recipient['deleted'] ?? false) || (bool) ($recipient['disable'] ?? false)) {
            return false;
        }

        if (!$this->hasOptedIn($recipient)) {
            return false;
        }

        $email = is_string($recipient['email'] ?? null) ? trim($recipient['email']) : '';

        return false !== filter_var($email, \FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param array<string, mixed> $recipient
     */
    private function hasOptedIn(array $recipient): bool
    {
        $optedIntoDigest = !array_key_exists(Configuration::FIELD_USER_DIGEST, $recipient) || (bool) $recipient[Configuration::FIELD_USER_DIGEST];

        return $optedIntoDigest && (bool) ($recipient[Configuration::FIELD_USER_IMMEDIATE_EMAIL] ?? false);
    }
}
