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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification\Channel;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\ImmediateEmailChannel;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ImmediateEmailChannelTest.
 *
 * Functional coverage for {@see ImmediateEmailChannel::supports()}'s full eligibility truth
 * table (issue #306): the extension configuration toggle, the recipient's existence/disabled
 * state, and both User Settings toggles (`tx_ximatypo3contentplanner_digest` from #302 and the
 * new `tx_ximatypo3contentplanner_immediate_email`) all have to agree before a notification is
 * handed to {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ImmediateEmailChannelTest extends AbstractFunctionalTestCase
{
    private ImmediateEmailChannel $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_users_immediate.csv');
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_IMMEDIATE_EMAIL, '1');

        $this->subject = $this->get(ImmediateEmailChannel::class);
    }

    #[Test]
    public function supportsIsFalseWhenTheChannelIsDisabled(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_IMMEDIATE_EMAIL, '0');

        self::assertFalse($this->subject->supports($this->buildNotification(2)));
    }

    #[Test]
    public function supportsIsTrueForAFullyEligibleRecipient(): void
    {
        self::assertTrue($this->subject->supports($this->buildNotification(2)));
    }

    #[Test]
    public function supportsIsFalseWithoutAValidEmailAddress(): void
    {
        self::assertFalse($this->subject->supports($this->buildNotification(3)));
    }

    #[Test]
    public function supportsIsFalseWhenOptedOutOfEmailAltogether(): void
    {
        self::assertFalse($this->subject->supports($this->buildNotification(4)));
    }

    #[Test]
    public function supportsIsFalseWhenStillOnTheDailyDigestInsteadOfImmediate(): void
    {
        self::assertFalse($this->subject->supports($this->buildNotification(5)));
    }

    #[Test]
    public function supportsIsFalseForADisabledBackendUser(): void
    {
        self::assertFalse($this->subject->supports($this->buildNotification(6)));
    }

    #[Test]
    public function supportsIsFalseForADeletedBackendUser(): void
    {
        self::assertFalse($this->subject->supports($this->buildNotification(7)));
    }

    #[Test]
    public function supportsIsFalseForARecipientThatDoesNotExist(): void
    {
        self::assertFalse($this->subject->supports($this->buildNotification(99)));
    }

    private function buildNotification(int $recipientUid): Notification
    {
        return new Notification(
            $recipientUid,
            NotificationEventType::StatusChanged,
            'pages',
            1,
            1,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home', 'previousStatus' => null, 'newStatus' => 'Draft'],
            1000,
        );
    }
}
