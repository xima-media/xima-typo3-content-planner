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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

class VisibilityUtilityTest extends TestCase
{
    private BackendUserAuthentication&MockObject $backendUserMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backendUserMock = $this->createMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $this->backendUserMock;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    public function testCheckContentStatusVisibilityReturnsTrueForAdmin(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(true);

        // Admin doesn't need custom_options check, so we don't expect it to be called
        $this->backendUserMock
            ->expects(self::never())
            ->method('check');

        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => 0];

        self::assertTrue(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsTrueForUserWithPermission(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUserMock
            ->expects(self::once())
            ->method('check')
            ->with('custom_options', 'tx_ximatypo3contentplanner:content-status')
            ->willReturn(true);

        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => 0];

        self::assertTrue(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsFalseForUserWithoutPermission(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUserMock
            ->expects(self::once())
            ->method('check')
            ->with('custom_options', 'tx_ximatypo3contentplanner:content-status')
            ->willReturn(false);

        self::assertFalse(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsFalseWhenUserHidesFeature(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => 1];

        self::assertFalse(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsFalseForNonAdminWithoutPermissionAndUserHides(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUserMock
            ->expects(self::once())
            ->method('check')
            ->with('custom_options', 'tx_ximatypo3contentplanner:content-status')
            ->willReturn(false);

        // User setting doesn't matter if permission check fails first
        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => 1];

        self::assertFalse(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityReturnsFalseForUserWithPermissionButHides(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->backendUserMock
            ->expects(self::once())
            ->method('check')
            ->with('custom_options', 'tx_ximatypo3contentplanner:content-status')
            ->willReturn(true);

        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => 1];

        self::assertFalse(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityWithMissingHideSetting(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(true);

        // Test when the hide setting doesn't exist in user array
        $this->backendUserMock->user = [];

        self::assertTrue(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityWithNullHideSetting(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(true);

        // Test when the hide setting is null
        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => null];

        self::assertTrue(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityWithZeroStringHideSetting(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(true);

        // Test when the hide setting is string '0'
        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => '0'];

        self::assertTrue(VisibilityUtility::checkContentStatusVisibility());
    }

    public function testCheckContentStatusVisibilityWithOneStringHideSetting(): void
    {
        $this->backendUserMock
            ->expects(self::once())
            ->method('isAdmin')
            ->willReturn(true);

        // Test when the hide setting is string '1' - should return true because strict comparison === 1 fails with string
        $this->backendUserMock->user = ['tx_ximatypo3contentplanner_hide' => '1'];

        self::assertTrue(VisibilityUtility::checkContentStatusVisibility());
    }
}
