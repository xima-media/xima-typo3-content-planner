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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\SysFileMetadataRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * SysFileMetadataRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class SysFileMetadataRepositoryTest extends AbstractFunctionalTestCase
{
    private SysFileMetadataRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_file_metadata.csv');
        $this->subject = $this->get(SysFileMetadataRepository::class);
    }

    #[Test]
    public function findByFileUidReturnsJoinedMetadata(): void
    {
        $result = $this->subject->findByFileUid(1);

        self::assertIsArray($result);
        self::assertSame('example.pdf', $result['title']);
        self::assertSame(1, (int) $result['file_uid']);
        self::assertSame(2, (int) $result['tx_ximatypo3contentplanner_status']);
    }

    #[Test]
    public function findByFileUidReturnsFalseForUnknownFile(): void
    {
        self::assertFalse($this->subject->findByFileUid(999));
    }

    #[Test]
    public function findByUidReturnsJoinedMetadata(): void
    {
        $result = $this->subject->findByUid(2);

        self::assertIsArray($result);
        self::assertSame('image.jpg', $result['title']);
        self::assertSame(2, (int) $result['file_uid']);
    }

    #[Test]
    public function findByUidReturnsFalseForUnknownUid(): void
    {
        self::assertFalse($this->subject->findByUid(999));
    }

    #[Test]
    public function findByIdentifierReturnsMatchingMetadata(): void
    {
        $result = $this->subject->findByIdentifier('/user_upload/example.pdf');

        self::assertIsArray($result);
        self::assertSame(1, (int) $result['uid']);
        self::assertSame('example.pdf', $result['title']);
    }

    #[Test]
    public function findByIdentifierReturnsFalseForUnknownIdentifier(): void
    {
        self::assertFalse($this->subject->findByIdentifier('/user_upload/ghost.txt'));
    }

    #[Test]
    public function updateStatusChangesStatusOnly(): void
    {
        $this->subject->updateStatus(2, 3);

        $record = $this->subject->findByUid(2);
        self::assertIsArray($record);
        self::assertSame(3, (int) $record['tx_ximatypo3contentplanner_status']);
        self::assertSame(0, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function updateStatusUpdatesAssigneeWhenProvided(): void
    {
        $this->subject->updateStatus(2, 3, 4);

        $record = $this->subject->findByUid(2);
        self::assertIsArray($record);
        self::assertSame(4, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function findFilesByFolderReturnsEmptyForUnknownStorage(): void
    {
        self::assertSame([], $this->subject->findFilesByFolder('99:/missing/'));
    }

    #[Test]
    public function findByFolderWithStatusReturnsEmptyForUnknownStorage(): void
    {
        self::assertSame([], $this->subject->findByFolderWithStatus('99:/missing/'));
    }
}
