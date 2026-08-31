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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{BackendUser, Status};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\PlannerService;

/**
 * PlannerServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class PlannerServiceTest extends TestCase
{
    private StatusRepository&\PHPUnit\Framework\MockObject\MockObject $statusRepository;
    private RecordRepository&\PHPUnit\Framework\MockObject\MockObject $recordRepository;
    private CommentRepository&\PHPUnit\Framework\MockObject\MockObject $commentRepository;
    private BackendUserRepository&\PHPUnit\Framework\MockObject\MockObject $backendUserRepository;
    private PlannerService $subject;

    protected function setUp(): void
    {
        $this->statusRepository = $this->createMock(StatusRepository::class);
        $this->recordRepository = $this->createMock(RecordRepository::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->backendUserRepository = $this->createMock(BackendUserRepository::class);

        $this->subject = new PlannerService(
            $this->statusRepository,
            $this->recordRepository,
            $this->commentRepository,
            $this->backendUserRepository,
        );

        $this->stubExtensionConfigurationForRecordTableCheck();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function getListOfStatusDelegatesToStatusRepository(): void
    {
        $expected = [new Status(uid: 1, title: 'Draft', icon: '', color: '')];
        $this->statusRepository->expects(self::once())->method('findAll')->willReturn($expected);

        self::assertSame($expected, $this->subject->getListOfStatus());
    }

    #[Test]
    public function getStatusWithStringIdentifierDelegatesToFindByTitle(): void
    {
        $status = new Status(uid: 0, title: 'In Progress', icon: '', color: '');
        $this->statusRepository->expects(self::once())->method('findByTitle')->with('In Progress')->willReturn($status);
        $this->statusRepository->expects(self::never())->method('findByUid');

        self::assertSame($status, $this->subject->getStatus('In Progress'));
    }

    #[Test]
    public function getStatusWithIntIdentifierDelegatesToFindByUid(): void
    {
        $status = new Status(uid: 3, title: '', icon: '', color: '');
        $this->statusRepository->expects(self::once())->method('findByUid')->with(3)->willReturn($status);
        $this->statusRepository->expects(self::never())->method('findByTitle');

        self::assertSame($status, $this->subject->getStatus(3));
    }

    #[Test]
    public function getStatusOfRecordReturnsStatusForExistingRecord(): void
    {
        $status = new Status(uid: 5, title: '', icon: '', color: '');
        $this->recordRepository->method('findByUid')->with('pages', 1)->willReturn([
            'uid' => 1,
            Configuration::FIELD_STATUS => 5,
        ]);
        $this->statusRepository->expects(self::once())->method('findByUid')->with(5)->willReturn($status);

        self::assertSame($status, $this->subject->getStatusOfRecord('pages', 1));
    }

    #[Test]
    public function getStatusOfRecordThrowsForUnregisteredTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(9518991865);

        $this->subject->getStatusOfRecord('tx_not_registered_table', 1);
    }

    #[Test]
    public function getStatusOfRecordThrowsWhenRecordNotFound(): void
    {
        $this->recordRepository->method('findByUid')->with('pages', 999)->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(4064696674);

        $this->subject->getStatusOfRecord('pages', 999);
    }

    #[Test]
    public function updateStatusForRecordResolvesStatusEntityToUid(): void
    {
        $this->stubExistingPageRecord();
        $status = new Status(uid: 7, title: '', icon: '', color: '');

        $this->recordRepository->expects(self::once())
            ->method('updateStatusByUid')
            ->with('pages', 1, 7, null);

        $this->subject->updateStatusForRecord('pages', 1, $status);
    }

    #[Test]
    public function updateStatusForRecordResolvesStatusTitleToUid(): void
    {
        $this->stubExistingPageRecord();
        $status = new Status(uid: 2, title: '', icon: '', color: '');
        $this->statusRepository->expects(self::once())->method('findByTitle')->with('Done')->willReturn($status);

        $this->recordRepository->expects(self::once())
            ->method('updateStatusByUid')
            ->with('pages', 1, 2, null);

        $this->subject->updateStatusForRecord('pages', 1, 'Done');
    }

    #[Test]
    public function updateStatusForRecordAcceptsRawStatusUid(): void
    {
        $this->stubExistingPageRecord();

        $this->recordRepository->expects(self::once())
            ->method('updateStatusByUid')
            ->with('pages', 1, 4, null);

        $this->subject->updateStatusForRecord('pages', 1, 4);
    }

    #[Test]
    public function updateStatusForRecordThrowsForZeroStatus(): void
    {
        $this->stubExistingPageRecord();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(9220772840);

        $this->subject->updateStatusForRecord('pages', 1, 0);
    }

    #[Test]
    public function updateStatusForRecordResolvesBackendUserAssigneeToUid(): void
    {
        $this->stubExistingPageRecord();
        $assignee = $this->createMock(BackendUser::class);
        $assignee->method('getUid')->willReturn(9);

        $this->recordRepository->expects(self::once())
            ->method('updateStatusByUid')
            ->with('pages', 1, 4, 9);

        $this->subject->updateStatusForRecord('pages', 1, 4, $assignee);
    }

    #[Test]
    public function updateStatusForRecordResolvesAssigneeUsernameToUid(): void
    {
        $this->stubExistingPageRecord();
        $this->backendUserRepository->expects(self::once())
            ->method('findByUsername')
            ->with('admin')
            ->willReturn(['uid' => 6]);

        $this->recordRepository->expects(self::once())
            ->method('updateStatusByUid')
            ->with('pages', 1, 4, 6);

        $this->subject->updateStatusForRecord('pages', 1, 4, 'admin');
    }

    #[Test]
    public function getCommentsOfRecordDelegatesToCommentRepository(): void
    {
        $this->stubExistingPageRecord();
        $expected = [['content' => 'A comment']];
        $this->commentRepository->expects(self::once())
            ->method('findAllByRecord')
            ->with(1, 'pages', true, showResolved: true)
            ->willReturn($expected);

        self::assertSame($expected, $this->subject->getCommentsOfRecord('pages', 1, true, true));
    }

    #[Test]
    public function clearCommentsOfRecordDelegatesToCommentRepository(): void
    {
        $this->stubExistingPageRecord();
        $this->commentRepository->expects(self::once())
            ->method('deleteAllCommentsByRecord')
            ->with(1, 'pages', 'foo');

        $this->subject->clearCommentsOfRecord('pages', 1, 'foo');
    }

    #[Test]
    public function addCommentsToRecordThrowsForUnresolvableAuthor(): void
    {
        $this->stubExistingPageRecord(['uid' => 1, 'pid' => 0]);
        $this->backendUserRepository->method('findByUsername')->with('ghost')->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(4723563571);

        $this->subject->addCommentsToRecord('pages', 1, 'Comment', 'ghost');
    }

    #[Test]
    public function addCommentsToRecordThrowsForMismatchedParentComment(): void
    {
        $this->stubExistingPageRecord(['uid' => 1, 'pid' => 0]);
        $this->commentRepository->method('findByUid')->with(99)->willReturn([
            'foreign_table' => 'tt_content',
            'foreign_uid' => 5,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(4723563572);

        $this->subject->addCommentsToRecord('pages', 1, 'Reply', 1, 99);
    }

    #[Test]
    public function addCommentsToRecordCreatesCommentViaDataHandler(): void
    {
        $this->stubExistingPageRecord(['uid' => 1, 'pid' => 0]);

        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->expects(self::once())
            ->method('start')
            ->with(
                self::callback(static function (array $data): bool {
                    $entry = reset($data[Configuration::TABLE_COMMENT]);

                    return 'A brand new comment' === $entry['content']
                        && 1 === $entry['foreign_uid']
                        && 'pages' === $entry['foreign_table']
                        && 42 === $entry['author']
                        && 0 === $entry['parent_uid'];
                }),
                [],
            );
        $dataHandler->expects(self::once())->method('process_datamap');
        GeneralUtility::addInstance(DataHandler::class, $dataHandler);

        $this->subject->addCommentsToRecord('pages', 1, 'A brand new comment', 42);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function stubExistingPageRecord(array $record = ['uid' => 1]): void
    {
        $this->recordRepository->method('findByUid')->with('pages', $record['uid'])->willReturn($record);
    }

    /**
     * PlannerService::preCheckRecordTable() delegates to ExtensionUtility::isRegisteredRecordTable(),
     * which reads two feature flags via ExtensionConfiguration - queue a stub for each read so tests
     * that reach the precheck don't need a full TYPO3 container bootstrap.
     */
    private function stubExtensionConfigurationForRecordTableCheck(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with(Configuration::EXT_KEY)->willReturn([]);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);
        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfiguration);
    }
}
