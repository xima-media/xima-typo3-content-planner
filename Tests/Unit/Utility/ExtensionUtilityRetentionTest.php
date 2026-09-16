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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

/**
 * ExtensionUtilityRetentionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ExtensionUtilityRetentionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);
    }

    #[Test]
    public function anExplicitZeroDoesNotTurnRetentionIntoDeleteEverything(): void
    {
        // The retention value ends up in "older than now minus N days". Zero would match
        // rows written seconds ago, so the whole table would go on the next cleanup run.
        $this->setSetting(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, '0');
        $this->setSetting(Configuration::CONF_NOTIFICATION_RETENTION_UNREAD_DAYS, '0');

        self::assertSame(30, ExtensionUtility::getNotificationRetentionReadDays());
        self::assertSame(90, ExtensionUtility::getNotificationRetentionUnreadDays());
    }

    #[Test]
    public function aNegativeOrNonNumericValueIsTreatedTheSameWay(): void
    {
        $this->setSetting(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, '-5');
        self::assertSame(30, ExtensionUtility::getNotificationRetentionReadDays());

        $this->setSetting(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, 'soon');
        self::assertSame(30, ExtensionUtility::getNotificationRetentionReadDays());
    }

    #[Test]
    public function anExplicitValueOfAtLeastOneDayIsHonoured(): void
    {
        $this->setSetting(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, '1');
        self::assertSame(1, ExtensionUtility::getNotificationRetentionReadDays());

        $this->setSetting(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, '7');
        self::assertSame(7, ExtensionUtility::getNotificationRetentionReadDays());
    }

    private function setSetting(string $key, string $value): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][$key] = $value;
    }
}
