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
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\DropDownSelectionService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * DropDownSelectionServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DropDownSelectionServiceTest extends AbstractFunctionalTestCase
{
    private DropDownSelectionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('comments.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('record_edit', ['id' => 1]);
        $this->subject = $this->get(DropDownSelectionService::class);
    }

    #[Test]
    public function generateSelectionReturnsDropDownItemsForPageWithStatus(): void
    {
        $result = $this->subject->generateSelection('pages', 1);

        self::assertIsArray($result);
        self::assertArrayHasKey('header', $result);
        self::assertArrayHasKey('reset', $result);
        self::assertArrayHasKey('assignee', $result);
        self::assertArrayHasKey('comments', $result);
    }

    #[Test]
    public function addHeaderItemToSelectionAddsHeaderComponent(): void
    {
        $entries = [];
        $this->subject->addHeaderItemToSelection($entries);

        self::assertArrayHasKey('header', $entries);
        self::assertArrayHasKey('headerDivider', $entries);
    }

    #[Test]
    public function addDividerItemToSelectionAddsDivider(): void
    {
        $entries = [];
        $this->subject->addDividerItemToSelection($entries);

        self::assertArrayHasKey('divider', $entries);
    }

    #[Test]
    public function addAssigneeItemToSelectionAddsAssigneeComponent(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_ASSIGNEE => 1];
        $this->subject->addAssigneeItemToSelection($entries, $record, 'pages', 1);

        self::assertArrayHasKey('assignee', $entries);
    }

    #[Test]
    public function addCommentsItemToSelectionAddsCommentsComponent(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_COMMENTS => 1];
        $this->subject->addCommentsItemToSelection($entries, $record, 'pages', 1);

        self::assertArrayHasKey('comments', $entries);
    }
}
