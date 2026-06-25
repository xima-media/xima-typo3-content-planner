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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Command\BulkUpdateCommand;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * BulkUpdateCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class BulkUpdateCommandTest extends AbstractFunctionalTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();

        $command = new BulkUpdateCommand(
            $this->get(StatusRepository::class),
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(FolderStatusRepository::class),
        );
        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function updatesSinglePageStatus(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '2',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Updated 1 "pages" records to status "In Progress".', $this->tester->getDisplay());

        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(2, (int) $record[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function updatesPagesRecursively(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '3',
            '--recursive' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Updated 3 "pages" records', $this->tester->getDisplay());

        $repository = $this->get(RecordRepository::class);
        self::assertSame(3, (int) $repository->findByUid('pages', 1)[Configuration::FIELD_STATUS]);
        self::assertSame(3, (int) $repository->findByUid('pages', 2)[Configuration::FIELD_STATUS]);
        self::assertSame(3, (int) $repository->findByUid('pages', 3)[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function updatesStatusWithAssignee(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '2',
            '--assignee' => '1',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertSame(1, (int) $record[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function clearsAssigneeWhenAssigneeIsZero(): void
    {
        $this->get(RecordRepository::class)->updateStatusByUid('pages', 1, 2, 1);

        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '2',
            '--assignee' => '0',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertNull($record[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function clearsStatusWhenStatusIsZero(): void
    {
        $this->get(RecordRepository::class)->updateStatusByUid('pages', 1, 2, 1);

        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '0',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('status "clear"', $this->tester->getDisplay());

        $record = $this->get(RecordRepository::class)->findByUid('pages', 1);
        self::assertNull($record[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function failsForUnknownStatusUid(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '999',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Status with uid 999 not found.', $this->tester->getDisplay());
    }

    #[Test]
    public function failsForInvalidAssigneeValue(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'pages',
            'uid' => '1',
            'status' => '2',
            '--assignee' => 'not-an-int',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Invalid assignee value', $this->tester->getDisplay());
    }

    #[Test]
    public function updatesFolderStatusViaCombinedIdentifier(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'folder',
            'uid' => '1:/user_upload/new/',
            'status' => '2',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Updated folder "1:/user_upload/new/" to status "In Progress".', $this->tester->getDisplay());

        $folder = $this->get(FolderStatusRepository::class)->findByCombinedIdentifier('1:/user_upload/new/');
        self::assertIsArray($folder);
        self::assertSame(2, (int) $folder[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function failsForInvalidFolderIdentifier(): void
    {
        $exitCode = $this->tester->execute([
            'table' => 'folder',
            'uid' => 'no-colon-here',
            'status' => '2',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Invalid folder identifier', $this->tester->getDisplay());
    }

    #[Test]
    public function clearingStatusDeletesCommentsWhenFeatureEnabled(): void
    {
        $previous = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] ?? [];
        try {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET] = '1';
            GeneralUtility::makeInstance(ExtensionConfiguration::class)->set(Configuration::EXT_KEY, $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);

            $exitCode = $this->tester->execute([
                'table' => 'pages',
                'uid' => '1',
                'status' => '0',
            ]);
        } finally {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = $previous;
            GeneralUtility::makeInstance(ExtensionConfiguration::class)->set(Configuration::EXT_KEY, $previous);
        }

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(0, $this->get(CommentRepository::class)->countAllByRecord(1, 'pages'));
    }
}
