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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ContextMenuSelectionService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ContextMenuSelectionServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContextMenuSelectionServiceTest extends AbstractFunctionalTestCase
{
    private ContextMenuSelectionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('comments.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('record_edit', ['id' => 1]);
        $this->subject = $this->get(ContextMenuSelectionService::class);
    }

    #[Test]
    public function addHeaderItemToSelectionIsNoOp(): void
    {
        $entries = [];
        $this->subject->addHeaderItemToSelection($entries);

        self::assertSame([], $entries);
    }

    #[Test]
    public function generateSelectionReturnsContextMenuArrayForPageWithStatus(): void
    {
        $result = $this->subject->generateSelection('pages', 1);

        self::assertIsArray($result);
        // No header for context menu, but status items, reset and actions.
        self::assertArrayNotHasKey('header', $result);
        self::assertArrayHasKey('reset', $result);
        self::assertArrayHasKey('assignee', $result);
        self::assertArrayHasKey('comments', $result);
        self::assertSame('change', $result['1']['callbackAction']);
    }

    #[Test]
    public function addStatusResetItemToSelectionAddsResetEntry(): void
    {
        $entries = [];
        $this->subject->addStatusResetItemToSelection($entries, 'pages', 1);

        self::assertSame('reset', $entries['reset']['callbackAction']);
    }

    #[Test]
    public function addDividerItemToSelectionAddsDividerType(): void
    {
        $entries = [];
        $this->subject->addDividerItemToSelection($entries);

        self::assertSame(['type' => 'divider'], $entries['divider']);
    }

    #[Test]
    public function addAssigneeItemToSelectionAddsAssigneeWithCurrentAssignee(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_ASSIGNEE => 1];
        $this->subject->addAssigneeItemToSelection($entries, $record, 'pages', 1);

        self::assertSame('assignee', $entries['assignee']['callbackAction']);
        self::assertSame(1, $entries['assignee']['currentAssignee']);
    }

    #[Test]
    public function addCommentsItemToSelectionAddsCommentsEntry(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_COMMENTS => 1];
        $this->subject->addCommentsItemToSelection($entries, $record, 'pages', 1);

        self::assertSame('comments', $entries['comments']['callbackAction']);
    }

    #[Test]
    public function addFolderStatusResetItemToSelectionAddsResetEntry(): void
    {
        $entries = [];
        $this->subject->addFolderStatusResetItemToSelection($entries, '1:/user_upload/');

        self::assertSame('reset', $entries['reset']['callbackAction']);
    }
}
