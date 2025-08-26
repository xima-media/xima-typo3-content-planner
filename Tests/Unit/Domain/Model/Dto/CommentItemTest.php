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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Model\Dto;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

class CommentItemTest extends TestCase
{
    private CommentItem $subject;
    /** @var array<string, mixed> */
    private array $testData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testData = [
            'uid' => 123,
            'foreign_table' => 'pages',
            'foreign_uid' => 456,
            'author' => 789,
            'crdate' => 1640995200, // 2022-01-01 00:00:00
            'edited' => 1,
            'resolved_date' => 1641081600, // 2022-01-02 00:00:00
            'resolved_user' => 999,
        ];

        $this->subject = CommentItem::create($this->testData);
    }

    public function testCreate(): void
    {
        $data = ['uid' => 1, 'title' => 'Test'];
        $item = CommentItem::create($data);

        self::assertInstanceOf(CommentItem::class, $item);
        self::assertSame($data, $item->data);
        self::assertSame([], $item->relatedRecord);
        self::assertNull($item->status);
    }

    public function testInitialState(): void
    {
        self::assertSame($this->testData, $this->subject->data);
        self::assertSame([], $this->subject->relatedRecord);
        self::assertNull($this->subject->status);
    }

    public function testIsEditedReturnsTrueWhenEdited(): void
    {
        self::assertTrue($this->subject->isEdited());
    }

    public function testIsEditedReturnsFalseWhenNotEdited(): void
    {
        $data = $this->testData;
        $data['edited'] = 0;
        $item = CommentItem::create($data);

        self::assertFalse($item->isEdited());
    }

    public function testIsEditedWithVariousValues(): void
    {
        $testCases = [
            [0, false],
            [1, true],
            ['0', false],
            ['1', true],
            [null, false],
            [false, false],
            [true, true],
            [2, true],
        ];

        foreach ($testCases as [$value, $expected]) {
            $data = $this->testData;
            $data['edited'] = $value;
            $item = CommentItem::create($data);

            self::assertSame($expected, $item->isEdited(), 'Failed for value: ' . var_export($value, true));
        }
    }

    public function testIsResolvedReturnsTrueWhenResolved(): void
    {
        self::assertTrue($this->subject->isResolved());
    }

    public function testIsResolvedReturnsFalseWhenNotResolved(): void
    {
        $data = $this->testData;
        $data['resolved_date'] = 0;
        $item = CommentItem::create($data);

        self::assertFalse($item->isResolved());
    }

    public function testIsResolvedWithVariousValues(): void
    {
        $testCases = [
            [0, false],
            [1, true],
            [-1, false], // Negative values should be false
            [1640995200, true],
            ['0', false],
            ['1', true],
            [null, false],
        ];

        foreach ($testCases as [$value, $expected]) {
            $data = $this->testData;
            $data['resolved_date'] = $value;
            $item = CommentItem::create($data);

            self::assertSame($expected, $item->isResolved(), 'Failed for resolved_date value: ' . var_export($value, true));
        }
    }

    public function testGetResolvedDate(): void
    {
        self::assertSame(1641081600, $this->subject->getResolvedDate());
    }

    public function testGetResolvedDateWithStringValue(): void
    {
        $data = $this->testData;
        $data['resolved_date'] = '1641081600';
        $item = CommentItem::create($data);

        self::assertSame(1641081600, $item->getResolvedDate());
    }

    public function testGetResolvedDateWithZero(): void
    {
        $data = $this->testData;
        $data['resolved_date'] = 0;
        $item = CommentItem::create($data);

        self::assertSame(0, $item->getResolvedDate());
    }

    // Skip testGetResolvedUserWhenResolved - requires TYPO3 backend context

    public function testGetResolvedUserWhenNotResolved(): void
    {
        $data = $this->testData;
        $data['resolved_date'] = 0;
        $item = CommentItem::create($data);

        $result = $item->getResolvedUser();

        self::assertSame('', $result);
    }

    public function testDataAccess(): void
    {
        // Test that we can access the data array properties
        self::assertSame(123, $this->subject->data['uid']);
        self::assertSame('pages', $this->subject->data['foreign_table']);
        self::assertSame(456, $this->subject->data['foreign_uid']);
        self::assertSame(789, $this->subject->data['author']);
        self::assertSame(1640995200, $this->subject->data['crdate']);
    }

    public function testRelatedRecordInitialState(): void
    {
        $item = CommentItem::create(['uid' => 1]);

        // Initially should be empty array
        self::assertSame([], $item->relatedRecord);

        // Can be set to false
        $item->relatedRecord = false;
        self::assertFalse($item->relatedRecord);

        // Can be set to array
        $testRecord = ['uid' => 456, 'title' => 'Test Page'];
        $item->relatedRecord = $testRecord;
        self::assertSame($testRecord, $item->relatedRecord);
    }

    public function testStatusProperty(): void
    {
        // Initially null
        self::assertNull($this->subject->status);

        // Can be set to Status object (we can't create real Status without dependencies)
        // So we just test that it can be assigned
        $this->subject->status = null;
        self::assertNull($this->subject->status);
    }

    public function testCreateWithEmptyData(): void
    {
        $item = CommentItem::create([]);

        self::assertInstanceOf(CommentItem::class, $item);
        self::assertSame([], $item->data);
        self::assertSame([], $item->relatedRecord);
        self::assertNull($item->status);
    }

    public function testCreateWithPartialData(): void
    {
        $partialData = [
            'uid' => 42,
            'foreign_table' => 'tt_content',
            // Missing other fields
        ];

        $item = CommentItem::create($partialData);

        self::assertSame($partialData, $item->data);
        self::assertSame(42, $item->data['uid']);
        self::assertSame('tt_content', $item->data['foreign_table']);

        // Test behavior with missing data
        self::assertFalse($item->isResolved()); // Should handle missing resolved_date
        self::assertFalse($item->isEdited());    // Should handle missing edited
    }
}
