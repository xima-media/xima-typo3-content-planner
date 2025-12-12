<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Utility\Compatibility;

use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\{DropDownDivider, DropDownHeader, DropDownItem};
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDownButton;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ComponentFactoryUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ComponentFactoryUtility
{
    public static function createDropDownButton(): DropDownButton
    {
        if (VersionUtility::is14OrHigher()) {
            return self::getComponentFactory()->createDropDownButton();
        }

        return GeneralUtility::makeInstance(DropDownButton::class);
    }

    public static function createDropDownItem(): DropDownItem
    {
        if (VersionUtility::is14OrHigher()) {
            return self::getComponentFactory()->createDropDownItem();
        }

        return GeneralUtility::makeInstance(DropDownItem::class);
    }

    public static function createDropDownDivider(): DropDownDivider
    {
        if (VersionUtility::is14OrHigher()) {
            return self::getComponentFactory()->createDropDownDivider();
        }

        return GeneralUtility::makeInstance(DropDownDivider::class);
    }

    public static function createDropDownHeader(): DropDownHeader
    {
        if (VersionUtility::is14OrHigher()) {
            return self::getComponentFactory()->createDropDownHeader();
        }

        return GeneralUtility::makeInstance(DropDownHeader::class);
    }

    /**
     * @return \TYPO3\CMS\Backend\Template\Components\ComponentFactory
     */
    private static function getComponentFactory(): object
    {
        // Use dynamic class name to avoid autoload issues on TYPO3 13
        $componentFactoryClass = 'TYPO3\\CMS\\Backend\\Template\\Components\\ComponentFactory';

        return GeneralUtility::makeInstance($componentFactoryClass);
    }
}
