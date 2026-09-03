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
use Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\DatabaseChannel;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * DatabaseChannelTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DatabaseChannelTest extends AbstractFunctionalTestCase
{
    private DatabaseChannel $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(DatabaseChannel::class);
    }

    #[Test]
    public function supportsIsTrueWhenTheChannelIsEnabled(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '1');

        self::assertTrue($this->subject->supports($this->buildNotification()));
    }

    #[Test]
    public function supportsIsFalseWhenTheChannelIsDisabled(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '0');

        self::assertFalse($this->subject->supports($this->buildNotification()));
    }

    #[Test]
    public function deliverPersistsANotificationRow(): void
    {
        $this->subject->deliver($this->buildNotification());

        self::assertSame(1, (int) $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->count('*', Configuration::TABLE_NOTIFICATION, []));
    }

    private function buildNotification(): Notification
    {
        return new Notification(
            2,
            NotificationEventType::StatusChanged,
            'pages',
            1,
            1,
            NotificationReason::WatchingSinceAssignment,
            ['version' => 1, 'title' => 'Home'],
            1000,
        );
    }
}
