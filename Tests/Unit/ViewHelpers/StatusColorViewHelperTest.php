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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\ViewHelpers\StatusColorViewHelper;

final class StatusColorViewHelperTest extends UnitTestCase
{
    /**
     * @return array<string, array{statusId: int, colorName: bool, statusExists: bool, statusColor: string|null, expectedResult: string}>
     */
    public static function renderDataProvider(): array
    {
        return [
            'status exists, return color name' => [
                'statusId' => 1,
                'colorName' => true,
                'statusExists' => true,
                'statusColor' => 'red',
                'expectedResult' => 'red',
            ],
            'status exists, return color code' => [
                'statusId' => 2,
                'colorName' => false,
                'statusExists' => true,
                'statusColor' => 'blue',
                'expectedResult' => 'rgb(100,187,200)',
            ],
            'status does not exist, return empty' => [
                'statusId' => 999,
                'colorName' => true,
                'statusExists' => false,
                'statusColor' => null,
                'expectedResult' => '',
            ],
            'status null, colorName true, return empty' => [
                'statusId' => 0,
                'colorName' => true,
                'statusExists' => false,
                'statusColor' => null,
                'expectedResult' => '',
            ],
            'status exists, colorName false (default)' => [
                'statusId' => 3,
                'colorName' => false,
                'statusExists' => true,
                'statusColor' => 'green',
                'expectedResult' => 'rgb(106,158,113)',
            ],
        ];
    }

    #[DataProvider('renderDataProvider')]
    #[Test]
    public function renderReturnsExpectedResult(
        int $statusId,
        bool $colorName,
        bool $statusExists,
        ?string $statusColor,
        string $expectedResult
    ): void {
        $statusMock = null;
        if ($statusExists && $statusColor !== null) {
            $statusMock = $this->createMock(Status::class);
            $statusMock->method('getColor')->willReturn($statusColor);
        }

        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $statusRepositoryMock
            ->method('findByUid')
            ->with($statusId)
            ->willReturn($statusMock);

        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'statusId' => $statusId,
            'colorName' => $colorName,
        ]);

        $result = $viewHelper->render();

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function initializeArgumentsRegistersStatusIdAsRequired(): void
    {
        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);

        $this->expectNotToPerformAssertions();
        $viewHelper->initializeArguments();
    }

    #[Test]
    public function escapeOutputIsFalse(): void
    {
        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);

        $reflection = new \ReflectionClass($viewHelper);
        $property = $reflection->getProperty('escapeOutput');
        $property->setAccessible(true);

        self::assertFalse($property->getValue($viewHelper));
    }

    #[Test]
    public function renderWithColorNameTrueReturnsColorString(): void
    {
        $statusMock = $this->createMock(Status::class);
        $statusMock->method('getColor')->willReturn('orange');

        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $statusRepositoryMock
            ->method('findByUid')
            ->with(5)
            ->willReturn($statusMock);

        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'statusId' => 5,
            'colorName' => true,
        ]);

        $result = $viewHelper->render();

        self::assertSame('orange', $result);
    }

    #[Test]
    public function renderWithColorNameFalseReturnsColorCode(): void
    {
        $statusMock = $this->createMock(Status::class);
        $statusMock->method('getColor')->willReturn('purple');

        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $statusRepositoryMock
            ->method('findByUid')
            ->with(7)
            ->willReturn($statusMock);

        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'statusId' => 7,
            'colorName' => false,
        ]);

        $result = $viewHelper->render();

        self::assertSame('rgb(92,107,192)', $result);
    }

    #[Test]
    public function renderUsesDefaultColorNameValue(): void
    {
        $statusMock = $this->createMock(Status::class);
        $statusMock->method('getColor')->willReturn('yellow');

        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $statusRepositoryMock
            ->method('findByUid')
            ->with(8)
            ->willReturn($statusMock);

        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'statusId' => 8,
            'colorName' => true, // Explicit default value
        ]);

        $result = $viewHelper->render();

        // Default colorName is true, so should return color name, not RGB code
        self::assertSame('yellow', $result);
    }

    #[Test]
    public function renderWithNullStatusReturnsEmptyString(): void
    {
        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $statusRepositoryMock
            ->method('findByUid')
            ->with(999)
            ->willReturn(null);

        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'statusId' => 999,
            'colorName' => false,
        ]);

        $result = $viewHelper->render();

        self::assertSame('', $result);
    }

    #[Test]
    public function renderCallsFindByUidWithCorrectStatusId(): void
    {
        $statusRepositoryMock = $this->createMock(StatusRepository::class);
        $statusRepositoryMock
            ->expects(self::once())
            ->method('findByUid')
            ->with(42)
            ->willReturn(null);

        $viewHelper = new StatusColorViewHelper($statusRepositoryMock);
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'statusId' => 42,
            'colorName' => true,
        ]);

        $result = $viewHelper->render();

        self::assertSame('', $result);
    }
}
