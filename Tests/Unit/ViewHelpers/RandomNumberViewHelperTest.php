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
use Xima\XimaTypo3ContentPlanner\ViewHelpers\RandomNumberViewHelper;

final class RandomNumberViewHelperTest extends UnitTestCase
{
    /**
     * @return array<string, array{arguments: array<string, int>, expectedMin: int, expectedMax: int}>
     */
    public static function argumentProvider(): array
    {
        return [
            'custom range (5 to 15)' => [
                'arguments' => ['min' => 5, 'max' => 15],
                'expectedMin' => 5,
                'expectedMax' => 15,
            ],
            'same min and max' => [
                'arguments' => ['min' => 7, 'max' => 7],
                'expectedMin' => 7,
                'expectedMax' => 7,
            ],
            'large range (1 to 1000)' => [
                'arguments' => ['min' => 1, 'max' => 1000],
                'expectedMin' => 1,
                'expectedMax' => 1000,
            ],
            'negative range (-10 to -5)' => [
                'arguments' => ['min' => -10, 'max' => -5],
                'expectedMin' => -10,
                'expectedMax' => -5,
            ],
            'mixed range (-5 to 5)' => [
                'arguments' => ['min' => -5, 'max' => 5],
                'expectedMin' => -5,
                'expectedMax' => 5,
            ],
            'default-like range (1 to 10)' => [
                'arguments' => ['min' => 1, 'max' => 10],
                'expectedMin' => 1,
                'expectedMax' => 10,
            ],
        ];
    }

    #[DataProvider('argumentProvider')]
    #[Test]
    public function renderReturnsNumberInExpectedRange(mixed $arguments, int $expectedMin, int $expectedMax): void
    {
        $viewHelper = new RandomNumberViewHelper();
        $viewHelper->initializeArguments();
        $viewHelper->setArguments($arguments);

        $result = $viewHelper->render();

        if ($expectedMin <= $expectedMax) {
            self::assertGreaterThanOrEqual($expectedMin, $result);
            self::assertLessThanOrEqual($expectedMax, $result);
        } else {
            self::assertGreaterThanOrEqual($expectedMax, $result);
            self::assertLessThanOrEqual($expectedMin, $result);
        }
    }

    #[Test]
    public function renderProducesVariousResults(): void
    {
        $viewHelper = new RandomNumberViewHelper();
        $viewHelper->initializeArguments();
        $viewHelper->setArguments(['min' => 1, 'max' => 100]);

        $results = [];
        for ($i = 0; $i < 50; $i++) {
            $results[] = $viewHelper->render();
        }

        $uniqueResults = array_unique($results);
        self::assertGreaterThan(1, count($uniqueResults), 'Expected multiple different random numbers');

        foreach ($results as $result) {
            self::assertGreaterThanOrEqual(1, $result);
            self::assertLessThanOrEqual(100, $result);
        }
    }

    #[Test]
    public function initializeArgumentsDoesNotThrowException(): void
    {
        $viewHelper = new RandomNumberViewHelper();

        $this->expectNotToPerformAssertions();
        $viewHelper->initializeArguments();
    }

    #[Test]
    public function escapeOutputIsFalse(): void
    {
        $viewHelper = new RandomNumberViewHelper();

        $reflection = new \ReflectionClass($viewHelper);
        $property = $reflection->getProperty('escapeOutput');
        $property->setAccessible(true);

        self::assertFalse($property->getValue($viewHelper));
    }
}
