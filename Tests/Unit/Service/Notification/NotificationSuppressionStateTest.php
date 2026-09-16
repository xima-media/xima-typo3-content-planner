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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationSuppressionState;

/**
 * NotificationSuppressionStateTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationSuppressionStateTest extends TestCase
{
    #[Test]
    public function isPausedIsFalseByDefault(): void
    {
        self::assertFalse((new NotificationSuppressionState())->isPaused());
    }

    #[Test]
    public function pauseSetsPausedState(): void
    {
        $subject = new NotificationSuppressionState();
        $subject->pause();

        self::assertTrue($subject->isPaused());
    }

    #[Test]
    public function resumeClearsPausedState(): void
    {
        $subject = new NotificationSuppressionState();
        $subject->pause();
        $subject->resume();

        self::assertFalse($subject->isPaused());
    }
}
