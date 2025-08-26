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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\ViewFactoryHelper;

class ViewFactoryHelperTest extends UnitTestCase
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

    public function testRenderViewCallsRenderView12ForVersion12(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock
            ->expects(self::once())
            ->method('getMajorVersion')
            ->willReturn(12);

        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $standaloneViewMock = $this->createMock(\TYPO3\CMS\Fluid\View\StandaloneView::class); // @phpstan-ignore classConstant.deprecatedClass
        $standaloneViewMock->expects(self::once())->method('setFormat')->with('html');
        $standaloneViewMock->expects(self::once())->method('setTemplateRootPaths');
        $standaloneViewMock->expects(self::once())->method('setPartialRootPaths');
        $standaloneViewMock->expects(self::once())->method('setTemplate')->with('TestTemplate');
        $standaloneViewMock->expects(self::once())->method('assignMultiple')->with(['key' => 'value']);
        $standaloneViewMock->expects(self::once())->method('render')->willReturn('<html>rendered content</html>');

        GeneralUtility::addInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class, $standaloneViewMock); // @phpstan-ignore classConstant.deprecatedClass

        $result = ViewFactoryHelper::renderView('TestTemplate', ['key' => 'value']);

        self::assertSame('<html>rendered content</html>', $result);
    }

    public function testRenderViewCallsRenderView13ForVersion13(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock
            ->expects(self::once())
            ->method('getMajorVersion')
            ->willReturn(13);

        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $viewMock = $this->createMock(\TYPO3\CMS\Core\View\ViewInterface::class);
        $viewMock->expects(self::once())->method('assignMultiple')->with(['key' => 'value']);
        $viewMock->expects(self::once())->method('render')->with('TestTemplate')->willReturn('<html>v13 content</html>');

        $viewFactoryMock = $this->createMock(\TYPO3\CMS\Core\View\ViewFactoryInterface::class);
        $viewFactoryMock->expects(self::once())->method('create')->willReturn($viewMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\View\ViewFactoryInterface::class, $viewFactoryMock);

        $result = ViewFactoryHelper::renderView('TestTemplate', ['key' => 'value']);

        self::assertSame('<html>v13 content</html>', $result);
    }

    public function testRenderViewCallsRenderView13ForFutureVersion(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock
            ->expects(self::once())
            ->method('getMajorVersion')
            ->willReturn(14);

        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $viewMock = $this->createMock(\TYPO3\CMS\Core\View\ViewInterface::class);
        $viewMock->method('assignMultiple');
        $viewMock->method('render')->willReturn('<html>future version</html>');

        $viewFactoryMock = $this->createMock(\TYPO3\CMS\Core\View\ViewFactoryInterface::class);
        $viewFactoryMock->method('create')->willReturn($viewMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\View\ViewFactoryInterface::class, $viewFactoryMock);

        $result = ViewFactoryHelper::renderView('TestTemplate', ['key' => 'value']);

        self::assertSame('<html>future version</html>', $result);
    }

    public function testRenderView12WithNonExtensionPath(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $standaloneViewMock = $this->createMock(\TYPO3\CMS\Fluid\View\StandaloneView::class); // @phpstan-ignore classConstant.deprecatedClass
        $standaloneViewMock->method('setFormat');
        $standaloneViewMock->method('setTemplateRootPaths');
        $standaloneViewMock->method('setPartialRootPaths');
        $standaloneViewMock->expects(self::once())->method('setTemplate')->with('SimpleTemplate');
        $standaloneViewMock->expects(self::never())->method('setTemplatePathAndFilename');
        $standaloneViewMock->method('assignMultiple');
        $standaloneViewMock->method('render')->willReturn('<html>simple template</html>');

        GeneralUtility::addInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class, $standaloneViewMock); // @phpstan-ignore classConstant.deprecatedClass

        $result = ViewFactoryHelper::renderView('SimpleTemplate', ['test' => 'data']);

        self::assertSame('<html>simple template</html>', $result);
    }

    public function testRenderView13WithRequest(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(13);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $viewMock = $this->createMock(\TYPO3\CMS\Core\View\ViewInterface::class);
        $viewMock->method('assignMultiple');
        $viewMock->method('render')->willReturn('<html>with request</html>');

        $viewFactoryMock = $this->createMock(\TYPO3\CMS\Core\View\ViewFactoryInterface::class);
        $viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(function ($viewFactoryData) use ($requestMock) {
                return $viewFactoryData->request === $requestMock;
            }))
            ->willReturn($viewMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\View\ViewFactoryInterface::class, $viewFactoryMock);

        $result = ViewFactoryHelper::renderView('TestTemplate', ['key' => 'value'], $requestMock);

        self::assertSame('<html>with request</html>', $result);
    }

    public function testRenderView13WithoutRequest(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(13);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $viewMock = $this->createMock(\TYPO3\CMS\Core\View\ViewInterface::class);
        $viewMock->method('assignMultiple');
        $viewMock->method('render')->willReturn('<html>without request</html>');

        $viewFactoryMock = $this->createMock(\TYPO3\CMS\Core\View\ViewFactoryInterface::class);
        $viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(function ($viewFactoryData) {
                return $viewFactoryData->request === null;
            }))
            ->willReturn($viewMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\View\ViewFactoryInterface::class, $viewFactoryMock);

        $result = ViewFactoryHelper::renderView('TestTemplate', ['key' => 'value']);

        self::assertSame('<html>without request</html>', $result);
    }

    public function testRenderViewWithEmptyValues(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(12);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $standaloneViewMock = $this->createMock(\TYPO3\CMS\Fluid\View\StandaloneView::class); // @phpstan-ignore classConstant.deprecatedClass
        $standaloneViewMock->method('setFormat');
        $standaloneViewMock->method('setTemplateRootPaths');
        $standaloneViewMock->method('setPartialRootPaths');
        $standaloneViewMock->method('setTemplate');
        $standaloneViewMock->expects(self::once())->method('assignMultiple')->with([]);
        $standaloneViewMock->method('render')->willReturn('<html>empty values</html>');

        GeneralUtility::addInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class, $standaloneViewMock); // @phpstan-ignore classConstant.deprecatedClass

        $result = ViewFactoryHelper::renderView('TestTemplate', []);

        self::assertSame('<html>empty values</html>', $result);
    }

    public function testRenderViewWithComplexValues(): void
    {
        $typo3VersionMock = $this->createMock(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(13);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $complexValues = [
            'string' => 'test string',
            'integer' => 42,
            'array' => ['nested' => 'value'],
            'boolean' => true,
            'null' => null,
        ];

        $viewMock = $this->createMock(\TYPO3\CMS\Core\View\ViewInterface::class);
        $viewMock->expects(self::once())->method('assignMultiple')->with($complexValues);
        $viewMock->method('render')->willReturn('<html>complex values</html>');

        $viewFactoryMock = $this->createMock(\TYPO3\CMS\Core\View\ViewFactoryInterface::class);
        $viewFactoryMock->method('create')->willReturn($viewMock);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\View\ViewFactoryInterface::class, $viewFactoryMock);

        $result = ViewFactoryHelper::renderView('TestTemplate', $complexValues);

        self::assertSame('<html>complex values</html>', $result);
    }
}
