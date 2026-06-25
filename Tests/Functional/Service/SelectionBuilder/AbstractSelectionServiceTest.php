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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\SelectionBuilder;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\NotImplementedException;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\{AbstractSelectionService, SelectionUriBuilder};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * AbstractSelectionServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AbstractSelectionServiceTest extends AbstractFunctionalTestCase
{
    private AbstractSelectionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
        $this->subject = new AbstractSelectionService(
            $this->get(StatusRepository::class),
            $this->get(RecordRepository::class),
            $this->get(StatusSelectionManager::class),
            $this->get(CommentRepository::class),
            $this->get(SelectionUriBuilder::class),
            $this->get(FolderStatusRepository::class),
        );
    }

    #[Test]
    public function addHeaderItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addHeaderItemToSelection($entries);
    }

    #[Test]
    public function addStatusItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addStatusItemToSelection($entries, $this->makeStatus());
    }

    #[Test]
    public function addStatusResetItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addStatusResetItemToSelection($entries);
    }

    #[Test]
    public function addAssigneeItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addAssigneeItemToSelection($entries, ['uid' => 1], 'pages', 1);
    }

    #[Test]
    public function addCommentsItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addCommentsItemToSelection($entries, ['uid' => 1], 'pages', 1);
    }

    #[Test]
    public function addCommentsTodoItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addCommentsTodoItemToSelection($entries, ['uid' => 1], 'pages', 1);
    }

    #[Test]
    public function addDividerItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addDividerItemToSelection($entries);
    }

    #[Test]
    public function addFolderStatusItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addFolderStatusItemToSelection($entries, $this->makeStatus(), null, '1:/foo/');
    }

    #[Test]
    public function addFolderStatusResetItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addFolderStatusResetItemToSelection($entries, '1:/foo/');
    }

    #[Test]
    public function addFolderAssigneeItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addFolderAssigneeItemToSelection($entries, ['uid' => 1], '1:/foo/');
    }

    #[Test]
    public function addFolderCommentsItemToSelectionThrowsNotImplemented(): void
    {
        $this->expectException(NotImplementedException::class);
        $entries = [];
        $this->subject->addFolderCommentsItemToSelection($entries, ['uid' => 1], '1:/foo/');
    }

    #[Test]
    public function shouldGenerateSelectionReturnsFalseForUnregisteredTable(): void
    {
        self::assertFalse($this->subject->shouldGenerateSelection('be_users'));
    }

    private function makeStatus(): Status
    {
        $status = new Status();
        $status->setTitle('Draft');
        $status->setIcon('flag');
        $status->setColor('blue');
        $status->_setProperty('uid', 1);

        return $status;
    }
}
