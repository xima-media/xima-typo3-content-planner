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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\ListSelectionService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ListSelectionServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ListSelectionServiceTest extends AbstractFunctionalTestCase
{
    private ListSelectionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importSharedDataSet('comments.csv');
        $this->importSharedDataSet('folders.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('record_edit', ['id' => 1]);
        $this->subject = $this->get(ListSelectionService::class);
    }

    #[Test]
    public function shouldGenerateSelectionReturnsTrueForRegisteredTable(): void
    {
        self::assertTrue($this->subject->shouldGenerateSelection('pages'));
    }

    #[Test]
    public function shouldGenerateSelectionReturnsFalseForUnregisteredTable(): void
    {
        self::assertFalse($this->subject->shouldGenerateSelection('be_users'));
    }

    #[Test]
    public function generateSelectionReturnsFalseForUnregisteredTable(): void
    {
        self::assertFalse($this->subject->generateSelection('be_users', 1));
    }

    #[Test]
    public function generateSelectionReturnsEntriesForPageWithStatus(): void
    {
        $result = $this->subject->generateSelection('pages', 1);

        self::assertIsArray($result);
        self::assertArrayHasKey('header', $result);
        // Current status (2) is excluded; statuses 1 and 3 remain.
        self::assertArrayHasKey('1', $result);
        self::assertArrayHasKey('3', $result);
        self::assertArrayNotHasKey('2', $result);
        // Record has a status, so a reset and additional actions are present.
        self::assertArrayHasKey('reset', $result);
        self::assertArrayHasKey('assignee', $result);
        self::assertArrayHasKey('comments', $result);
    }

    #[Test]
    public function generateSelectionForPageWithoutStatusOmitsAdditionalActions(): void
    {
        $result = $this->subject->generateSelection('pages', 2);

        self::assertIsArray($result);
        self::assertArrayHasKey('header', $result);
        self::assertArrayNotHasKey('assignee', $result);
        self::assertArrayNotHasKey('comments', $result);
    }

    #[Test]
    public function generateSelectionReturnsFalseWhenNoStatusesExist(): void
    {
        $this->get(StatusRepository::class);
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_ximatypo3contentplanner_domain_model_status');
        $connection->truncate('tx_ximatypo3contentplanner_domain_model_status');

        $freshSubject = $this->get(ListSelectionService::class);
        self::assertFalse($freshSubject->generateSelection('pages', 1));
    }

    #[Test]
    public function addHeaderItemToSelectionAddsHeaderAndDivider(): void
    {
        $entries = [];
        $this->subject->addHeaderItemToSelection($entries);

        self::assertArrayHasKey('header', $entries);
        self::assertArrayHasKey('headerDivider', $entries);
        self::assertStringContainsString('dropdown-header', $entries['header']);
    }

    #[Test]
    public function addDividerItemToSelectionUsesPostIdentifier(): void
    {
        $entries = [];
        $this->subject->addDividerItemToSelection($entries, '2');

        self::assertArrayHasKey('divider2', $entries);
    }

    #[Test]
    public function addAssigneeItemRendersUsernameLabel(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_ASSIGNEE => 1];
        $this->subject->addAssigneeItemToSelection($entries, $record, 'pages', 1);

        self::assertArrayHasKey('assignee', $entries);
        self::assertStringContainsString('data-content-planner-assignees', $entries['assignee']);
    }

    #[Test]
    public function addCommentsItemRendersCommentsEntry(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_COMMENTS => 1];
        $this->subject->addCommentsItemToSelection($entries, $record, 'pages', 1);

        self::assertArrayHasKey('comments', $entries);
        self::assertStringContainsString('data-content-planner-comments', $entries['comments']);
    }

    #[Test]
    public function addCommentsTodoItemToSelectionSkipsWhenNoTodos(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_COMMENTS => 0];
        $this->subject->addCommentsTodoItemToSelection($entries, $record, 'pages', 1);

        self::assertArrayNotHasKey('commentsTodo', $entries);
    }

    #[Test]
    public function addCommentsTodoItemRendersTodoCounts(): void
    {
        $entries = [];
        $record = ['uid' => 1, Configuration::FIELD_COMMENTS => 1];
        $this->subject->addCommentsTodoItemToSelection($entries, $record, 'pages', 1);

        self::assertArrayHasKey('commentsTodo', $entries);
        self::assertStringContainsString('1/3', $entries['commentsTodo']);
        self::assertStringContainsString('data-content-planner-comments', $entries['commentsTodo']);
    }

    #[Test]
    public function generateSelectionIncludesCommentsTodoWhenFeatureEnabled(): void
    {
        $extensionConfiguration = $this->get(ExtensionConfiguration::class);
        $previous = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] ?? [];
        try {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_COMMENT_TODOS] = '1';
            $extensionConfiguration->set(Configuration::EXT_KEY, $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);

            $result = $this->subject->generateSelection('pages', 1);
        } finally {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = $previous;
            $extensionConfiguration->set(Configuration::EXT_KEY, $previous);
        }

        self::assertIsArray($result);
        self::assertArrayHasKey('commentsTodo', $result);
    }

    #[Test]
    public function addStatusItemToSelectionTreatsStatusInstanceAsCurrentStatus(): void
    {
        $status = $this->makeStatus(1);
        $currentStatus = $this->makeStatus(1);
        $entries = [];
        $this->subject->addStatusItemToSelection($entries, $status, $currentStatus, 'pages', 1);

        self::assertArrayNotHasKey('1', $entries);
    }

    #[Test]
    public function addStatusItemToSelectionAddsEntryForDifferentStatusInstance(): void
    {
        $status = $this->makeStatus(1);
        $currentStatus = $this->makeStatus(3);
        $entries = [];
        $this->subject->addStatusItemToSelection($entries, $status, $currentStatus, 'pages', 1);

        self::assertArrayHasKey('1', $entries);
    }

    #[Test]
    public function generateSelectionForNonexistentRecordReturnsResetOnly(): void
    {
        $result = $this->subject->generateSelection('pages', 9999);

        self::assertIsArray($result);
        self::assertArrayHasKey('1', $result);
        self::assertArrayHasKey('2', $result);
        self::assertArrayHasKey('3', $result);
        self::assertArrayHasKey('reset', $result);
        self::assertArrayNotHasKey('assignee', $result);
        self::assertArrayNotHasKey('comments', $result);
    }

    #[Test]
    public function generateSelectionOmitsStatusChangesForViewOnlyUser(): void
    {
        // A view-only permission grants content status visibility but neither
        // status-change nor status-unset, so status entries and the reset
        // entry must both be omitted while assignee/comments remain visible.
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_view_only.csv');
        $backendUser = $this->setUpBackendUser(20);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $result = $this->subject->generateSelection('pages', 1);

        self::assertIsArray($result);
        self::assertArrayHasKey('header', $result);
        self::assertArrayNotHasKey('1', $result);
        self::assertArrayNotHasKey('3', $result);
        self::assertArrayNotHasKey('reset', $result);
        self::assertArrayHasKey('assignee', $result);
        self::assertArrayHasKey('comments', $result);
    }

    #[Test]
    public function addFolderAssigneeItemToSelectionRendersAssigneeEntry(): void
    {
        $entries = [];
        $folderRecord = ['uid' => 1, Configuration::FIELD_ASSIGNEE => 1];
        $this->subject->addFolderAssigneeItemToSelection($entries, $folderRecord, '1:/user_upload/');

        self::assertArrayHasKey('assignee', $entries);
        self::assertStringContainsString('data-content-planner-assignees', $entries['assignee']);
    }

    #[Test]
    public function addFolderCommentsItemToSelectionRendersCommentsEntry(): void
    {
        $entries = [];
        $folderRecord = ['uid' => 1, Configuration::FIELD_COMMENTS => 0];
        $this->subject->addFolderCommentsItemToSelection($entries, $folderRecord, '1:/user_upload/');

        self::assertArrayHasKey('comments', $entries);
        self::assertStringContainsString('data-content-planner-comments', $entries['comments']);
    }

    private function makeStatus(int $uid): Status
    {
        return new Status(uid: $uid, title: 'Status '.$uid, icon: 'flag', color: 'blue');
    }
}
