<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;

class IconHelperTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public function testGetDefaultIconSizeForTypo3Version12(): void
    {
        // Mock Typo3Version for v12
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock
            ->expects(self::once())
            ->method('getMajorVersion')
            ->willReturn(12);

        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getDefaultIconSize();

        // For TYPO3 v12, should return the deprecated constant
        self::assertIsString($result);
        self::assertSame('small', $result);
    }

    public function testGetDefaultIconSizeForTypo3Version13(): void
    {
        // Mock Typo3Version for v13
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock
            ->expects(self::once())
            ->method('getMajorVersion')
            ->willReturn(13);

        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getDefaultIconSize();

        // For TYPO3 v13, should return the enum
        self::assertInstanceOf(\TYPO3\CMS\Core\Imaging\IconSize::class, $result);
        self::assertSame(\TYPO3\CMS\Core\Imaging\IconSize::SMALL, $result);
    }

    public function testGetDefaultIconSizeForFutureVersion(): void
    {
        // Mock Typo3Version for future version
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock
            ->expects(self::once())
            ->method('getMajorVersion')
            ->willReturn(14);

        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getDefaultIconSize();

        // Should still use the new enum for versions >= 13
        self::assertInstanceOf(\TYPO3\CMS\Core\Imaging\IconSize::class, $result);
    }

    public function testGetIconByStatusWithNullStatus(): void
    {
        // Mock IconFactory
        $iconMock = $this->createMock(\TYPO3\CMS\Core\Imaging\Icon::class);
        $iconMock
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('flag-gray');

        $iconFactoryMock = $this->createMock(\TYPO3\CMS\Core\Imaging\IconFactory::class);
        $iconFactoryMock
            ->expects(self::once())
            ->method('getIcon')
            ->with('flag-gray', self::anything())
            ->willReturn($iconMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Imaging\IconFactory::class, $iconFactoryMock);

        // Mock Typo3Version
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getIconByStatus(null, false);

        self::assertSame('flag-gray', $result);
    }

    public function testGetIconByStatusWithStatusObject(): void
    {
        // Mock Status object
        $statusMock = $this->createMock(Status::class);
        $statusMock
            ->expects(self::once())
            ->method('getColoredIcon')
            ->willReturn('check-green');

        // Mock IconFactory
        $iconMock = $this->createMock(\TYPO3\CMS\Core\Imaging\Icon::class);
        $iconMock
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('check-green');

        $iconFactoryMock = $this->createMock(\TYPO3\CMS\Core\Imaging\IconFactory::class);
        $iconFactoryMock
            ->expects(self::once())
            ->method('getIcon')
            ->with('check-green', self::anything())
            ->willReturn($iconMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Imaging\IconFactory::class, $iconFactoryMock);

        // Mock Typo3Version
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getIconByStatus($statusMock, false);

        self::assertSame('check-green', $result);
    }

    public function testGetIconByStatusWithRenderTrue(): void
    {
        // Mock Status object
        $statusMock = $this->createMock(Status::class);
        $statusMock->method('getColoredIcon')->willReturn('warning-yellow');

        // Mock Icon
        $iconMock = $this->createMock(\TYPO3\CMS\Core\Imaging\Icon::class);
        $iconMock
            ->expects(self::once())
            ->method('render')
            ->willReturn('<span class="icon">warning-yellow</span>');

        $iconFactoryMock = $this->createMock(\TYPO3\CMS\Core\Imaging\IconFactory::class);
        $iconFactoryMock
            ->method('getIcon')
            ->willReturn($iconMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Imaging\IconFactory::class, $iconFactoryMock);

        // Mock Typo3Version
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getIconByStatus($statusMock, true);

        self::assertSame('<span class="icon">warning-yellow</span>', $result);
    }

    public function testGetIconByRecordWithFalseRecord(): void
    {
        $result = IconHelper::getIconByRecord('pages', false);

        self::assertSame('', $result);
    }

    public function testGetIconByRecordWithValidRecord(): void
    {
        $record = ['uid' => 123, 'title' => 'Test Page'];

        // Mock Icon
        $iconMock = $this->createMock(\TYPO3\CMS\Core\Imaging\Icon::class);
        $iconMock
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('apps-pagetree-page-default');

        $iconFactoryMock = $this->createMock(\TYPO3\CMS\Core\Imaging\IconFactory::class);
        $iconFactoryMock
            ->expects(self::once())
            ->method('getIconForRecord')
            ->with('pages', $record, self::anything())
            ->willReturn($iconMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Imaging\IconFactory::class, $iconFactoryMock);

        // Mock Typo3Version
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getIconByRecord('pages', $record, false);

        self::assertSame('apps-pagetree-page-default', $result);
    }

    public function testGetIconByRecordWithRenderTrue(): void
    {
        $record = ['uid' => 456, 'header' => 'Content Element'];

        // Mock Icon
        $iconMock = $this->createMock(\TYPO3\CMS\Core\Imaging\Icon::class);
        $iconMock
            ->expects(self::once())
            ->method('render')
            ->willReturn('<span class="icon">tt-content-element</span>');

        $iconFactoryMock = $this->createMock(\TYPO3\CMS\Core\Imaging\IconFactory::class);
        $iconFactoryMock
            ->method('getIconForRecord')
            ->willReturn($iconMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Imaging\IconFactory::class, $iconFactoryMock);

        // Mock Typo3Version
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = IconHelper::getIconByRecord('tt_content', $record, true);

        self::assertSame('<span class="icon">tt-content-element</span>', $result);
    }

    public function testGetAvatarByUserWithFalseUser(): void
    {
        $result = IconHelper::getAvatarByUser(false);

        self::assertSame('', $result);
    }

    public function testGetAvatarByUserWithValidUser(): void
    {
        $user = ['uid' => 1, 'username' => 'admin', 'realName' => 'Administrator'];

        // Mock Avatar
        $avatarMock = $this->createMock(\TYPO3\CMS\Backend\Backend\Avatar\Avatar::class);
        $avatarMock
            ->expects(self::once())
            ->method('render')
            ->with($user, 15, true)
            ->willReturn('<img class="avatar" src="avatar.png" />');

        GeneralUtility::addInstance(\TYPO3\CMS\Backend\Backend\Avatar\Avatar::class, $avatarMock);

        $result = IconHelper::getAvatarByUser($user);

        self::assertSame('<img class="avatar" src="avatar.png" />', $result);
    }

    public function testGetAvatarByUserWithCustomSize(): void
    {
        $user = ['uid' => 2, 'username' => 'editor'];

        // Mock Avatar
        $avatarMock = $this->createMock(\TYPO3\CMS\Backend\Backend\Avatar\Avatar::class);
        $avatarMock
            ->expects(self::once())
            ->method('render')
            ->with($user, 32, true)
            ->willReturn('<img class="avatar large" src="editor-avatar.png" />');

        GeneralUtility::addInstance(\TYPO3\CMS\Backend\Backend\Avatar\Avatar::class, $avatarMock);

        $result = IconHelper::getAvatarByUser($user, 32);

        self::assertSame('<img class="avatar large" src="editor-avatar.png" />', $result);
    }

    // Skip testing methods that depend on ContentUtility (getIconByStatusUid, getAvatarByUserId)
    // as they require database/repository mocking
}
