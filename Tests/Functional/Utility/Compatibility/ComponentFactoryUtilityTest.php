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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Compatibility;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\{DropDownDivider, DropDownHeader, DropDownItem};
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDownButton;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\{ComponentFactoryUtility, VersionUtility};

/**
 * ComponentFactoryUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ComponentFactoryUtilityTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function versionUtilityReturnsBoolMatchingRuntime(): void
    {
        self::assertIsBool(VersionUtility::is14OrHigher());
    }

    #[Test]
    public function createsDropDownButton(): void
    {
        self::assertInstanceOf(DropDownButton::class, ComponentFactoryUtility::createDropDownButton());
    }

    #[Test]
    public function createsDropDownItem(): void
    {
        self::assertInstanceOf(DropDownItem::class, ComponentFactoryUtility::createDropDownItem());
    }

    #[Test]
    public function createsDropDownDivider(): void
    {
        self::assertInstanceOf(DropDownDivider::class, ComponentFactoryUtility::createDropDownDivider());
    }

    #[Test]
    public function createsDropDownHeader(): void
    {
        self::assertInstanceOf(DropDownHeader::class, ComponentFactoryUtility::createDropDownHeader());
    }
}
