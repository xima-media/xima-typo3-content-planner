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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Channel;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\{ImmediateEmailRecipientEligibility, ImmediateEmailService};
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationChannelInterface;

use function is_array;

/**
 * ImmediateEmailChannel.
 *
 * Second (and final, per issue #306) email channel: a recipient opts into it via the
 * `tx_ximatypo3contentplanner_immediate_email` User Settings toggle, which only takes effect
 * while `tx_ximatypo3contentplanner_digest` (the #302 opt-out) is also on - one recipient never
 * gets both the daily digest and an immediate mail for the same event, see
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestService}'s matching skip.
 *
 * Kept thin like {@see DatabaseChannel}: all throttling/batching/sending is
 * {@see ImmediateEmailService}'s concern.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class ImmediateEmailChannel implements NotificationChannelInterface
{
    public function __construct(
        private BackendUserRepository $backendUserRepository,
        private ImmediateEmailService $immediateEmailService,
        private ImmediateEmailRecipientEligibility $eligibility,
    ) {}

    public function supports(Notification $notification): bool
    {
        $recipient = $this->fetchRecipient($notification);

        return is_array($recipient) && $this->eligibility->isEligible($recipient);
    }

    public function deliver(Notification $notification): void
    {
        $recipient = $this->fetchRecipient($notification);
        if (!is_array($recipient) || !$this->eligibility->isEligible($recipient)) {
            // Race between supports() and deliver(): the recipient vanished, was
            // disabled, or opted out in between.
            return;
        }

        $this->immediateEmailService->handle($notification, $recipient);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function fetchRecipient(Notification $notification): array|false
    {
        return $this->backendUserRepository->findByUid($notification->getRecipientUid());
    }
}
