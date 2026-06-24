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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Rendering;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconSize;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;

/**
 * IconUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class IconUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
    }

    #[Test]
    public function getIconByIdentifierRendersMarkup(): void
    {
        $markup = IconUtility::getIconByIdentifier('actions-close');

        self::assertStringContainsString('<span', $markup);
    }

    #[Test]
    public function getIconByStatusReturnsIdentifierByDefault(): void
    {
        $status = new Status();
        $status->setIcon('flag');
        $status->setColor('blue');

        self::assertSame('flag-blue', IconUtility::getIconByStatus($status));
    }

    #[Test]
    public function getIconByStatusRendersMarkupWhenRenderTrue(): void
    {
        $status = new Status();
        $status->setIcon('flag');
        $status->setColor('blue');

        self::assertStringContainsString('<span', IconUtility::getIconByStatus($status, true));
    }

    #[Test]
    public function getIconByStatusUsesFallbackForNull(): void
    {
        self::assertSame('flag-gray', IconUtility::getIconByStatus(null));
    }

    #[Test]
    public function getIconByStatusUidResolvesStatus(): void
    {
        self::assertSame('flag-blue', IconUtility::getIconByStatusUid(1));
    }

    #[Test]
    public function getIconByRecordReturnsEmptyStringForFalseRecord(): void
    {
        self::assertSame('', IconUtility::getIconByRecord('pages', false));
    }

    #[Test]
    public function getIconByRecordReturnsIdentifierForRecord(): void
    {
        $identifier = IconUtility::getIconByRecord('pages', ['uid' => 1, 'doktype' => 1]);

        self::assertNotSame('', $identifier);
    }

    #[Test]
    public function getAvatarByUserReturnsEmptyStringForFalseUser(): void
    {
        self::assertSame('', IconUtility::getAvatarByUser(false));
    }

    #[Test]
    public function getDefaultIconSizeReturnsSmall(): void
    {
        self::assertSame(IconSize::SMALL, IconUtility::getDefaultIconSize());
    }
}
