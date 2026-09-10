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

namespace Xima\XimaTypo3ContentPlannerDemoContent\Command;

use Doctrine\DBAL\ArrayParameterType;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\{Bootstrap, Environment};
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, StatusRepository};

use function array_map;
use function array_merge;
use function implode;
use function sprintf;

/**
 * SeedDemoContentCommand.
 *
 * Seeds a deterministic page tree carrying content-planner status, assignee
 * and comment fixtures, for the e2e suite to assert against.
 *
 * Idempotent by design: every run first removes any previously seeded pages
 * (matched by their fixed titles, see the DEMO_*_TITLE constants) and their
 * comments, then recreates them from scratch. Re-running the command is
 * therefore always safe and yields the same fixture state, even though the
 * underlying uids are not stable across runs - specs must reference fixtures
 * by the identifiers in Tests/Playwright/support/demo-content.ts, not by uid.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsCommand(
    name: 'content-planner:seed-demo-content',
    description: 'Idempotently seeds a deterministic demo page tree with content-planner status, assignee and comment fixtures for e2e tests.',
)]
final class SeedDemoContentCommand extends Command
{
    /** Root of the seeded demo tree, kept out of any real content the instance might carry. */
    public const ROOT_PAGE_TITLE = 'Content Planner E2E Demo';

    /** Carries a status, an assignee and one comment - covers all three tracking fields. */
    public const STATUS_PAGE_TITLE = 'Demo Page With Status';

    /** Carries a status only, no assignee and no comments. */
    public const DRAFT_PAGE_TITLE = 'Demo Page Draft';

    public const STATUS_TITLE_FOR_STATUS_PAGE = 'Needs review';
    public const STATUS_TITLE_FOR_DRAFT_PAGE = 'Pending';

    /** Matches the ddev addon's admin bootstrap (TYPO3_SETUP_ADMIN_USERNAME in docker-compose.typo3-setup.yaml). */
    public const ASSIGNEE_USERNAME = 'admin';

    public const COMMENT_CONTENT = '<p>Demo comment seeded for e2e tests.</p>';

    private const DEMO_PAGE_TITLES = [
        self::ROOT_PAGE_TITLE,
        self::STATUS_PAGE_TITLE,
        self::DRAFT_PAGE_TITLE,
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StatusRepository $statusRepository,
        private readonly BackendUserRepository $backendUserRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // This command deletes and recreates pages. It ships in a fixture extension that is
        // never part of the distributed package, so it should be unreachable in production
        // anyway - this is the second line of defence in case that ever stops being true.
        if (Environment::getContext()->isProduction()) {
            $output->writeln('<error>Refusing to seed demo content in the Production context.</error>');

            return Command::FAILURE;
        }

        // CommandApplication only instantiates the CLI backend user (see
        // TYPO3\CMS\Core\Core\Bootstrap::initializeBackendUser()), it does not
        // log it in. Without this, $GLOBALS['BE_USER']->isAdmin() is false and
        // DataHandler below rejects every write with "Attempt to modify table
        // ... without permission".
        Bootstrap::initializeBackendAuthentication();

        $assignee = $this->backendUserRepository->findByUsername(self::ASSIGNEE_USERNAME);
        if (false === $assignee) {
            $output->writeln(sprintf(
                '<error>No backend user "%s" found. Provision this instance via `ddev install` before seeding.</error>',
                self::ASSIGNEE_USERNAME,
            ));

            return Command::FAILURE;
        }

        $statusPageStatus = $this->statusRepository->findByTitle(self::STATUS_TITLE_FOR_STATUS_PAGE);
        $draftPageStatus = $this->statusRepository->findByTitle(self::STATUS_TITLE_FOR_DRAFT_PAGE);
        if (null === $statusPageStatus || null === $draftPageStatus) {
            $output->writeln(sprintf(
                '<error>Expected default content-planner statuses ("%s", "%s") are missing. Provision this instance via `ddev install` before seeding.</error>',
                self::STATUS_TITLE_FOR_STATUS_PAGE,
                self::STATUS_TITLE_FOR_DRAFT_PAGE,
            ));

            return Command::FAILURE;
        }

        $this->removeExistingDemoContent();

        $assigneeUid = (int) $assignee['uid'];
        $statusPageUid = $this->createDemoPages($assigneeUid, $statusPageStatus->getUid(), $draftPageStatus->getUid());
        $this->createDemoComment($statusPageUid, $assigneeUid);

        $output->writeln('<info>Demo content seeded.</info>');

        return Command::SUCCESS;
    }

    /**
     * Removes any previously seeded demo pages (matched by their fixed titles,
     * regardless of visibility/deleted state) and the comments attached to
     * them. A hard delete via the query builder, not DataHandler: these are
     * disposable e2e fixtures with no version/reference-index history worth
     * preserving, and a plain delete keeps re-seeding from ever accumulating
     * soft-deleted rows under the same titles.
     */
    /**
     * Removes a previously seeded demo tree so the command stays idempotent.
     *
     * Scoped to the seeded tree itself - the root page at pid 0 carrying the demo title,
     * plus whatever sits directly beneath it - rather than to every page whose title happens
     * to match. Matching on title alone across the whole page tree, with all restrictions
     * removed, would delete a real editor's page (and its comments) on a title collision.
     */
    private function removeExistingDemoContent(): void
    {
        $rootUid = $this->findDemoRootPageUid();
        if (null === $rootUid) {
            return;
        }

        $childQueryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $childQueryBuilder->getRestrictions()->removeAll();
        $childUids = $childQueryBuilder
            ->select('uid')
            ->from('pages')
            ->where($childQueryBuilder->expr()->eq(
                'pid',
                $childQueryBuilder->createNamedParameter($rootUid, Connection::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchFirstColumn();

        $existingUids = array_merge([$rootUid], array_map(intval(...), $childUids));

        $commentsQueryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_COMMENT);
        $commentsQueryBuilder
            ->delete(Configuration::TABLE_COMMENT)
            ->where(
                $commentsQueryBuilder->expr()->eq(
                    'foreign_table',
                    $commentsQueryBuilder->createNamedParameter('pages'),
                ),
                $commentsQueryBuilder->expr()->in(
                    'foreign_uid',
                    $commentsQueryBuilder->createNamedParameter($existingUids, ArrayParameterType::INTEGER),
                ),
            )
            ->executeStatement();

        $pagesDeleteQueryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $pagesDeleteQueryBuilder
            ->delete('pages')
            ->where($pagesDeleteQueryBuilder->expr()->in(
                'uid',
                $pagesDeleteQueryBuilder->createNamedParameter($existingUids, ArrayParameterType::INTEGER),
            ))
            ->executeStatement();
    }

    private function findDemoRootPageUid(): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $uid = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'title',
                    $queryBuilder->createNamedParameter(self::ROOT_PAGE_TITLE),
                ),
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                ),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return false === $uid ? null : (int) $uid;
    }

    /**
     * @return int uid of the created status page, the only one the caller needs (it's the one carrying the comment)
     */
    private function createDemoPages(int $assigneeUid, int $statusPageStatusUid, int $draftPageStatusUid): int
    {
        $data = [
            'pages' => [
                'NEW-root' => [
                    'pid' => 0,
                    'title' => self::ROOT_PAGE_TITLE,
                    'doktype' => 1,
                    'hidden' => 0,
                ],
                'NEW-status-page' => [
                    'pid' => 'NEW-root',
                    'title' => self::STATUS_PAGE_TITLE,
                    'doktype' => 1,
                    'hidden' => 0,
                    Configuration::FIELD_STATUS => $statusPageStatusUid,
                    Configuration::FIELD_ASSIGNEE => $assigneeUid,
                ],
                'NEW-draft-page' => [
                    'pid' => 'NEW-root',
                    'title' => self::DRAFT_PAGE_TITLE,
                    'doktype' => 1,
                    'hidden' => 0,
                    Configuration::FIELD_STATUS => $draftPageStatusUid,
                ],
            ],
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        $this->assertNoErrors($dataHandler, 'creating the demo page tree');

        return (int) $dataHandler->substNEWwithIDs['NEW-status-page'];
    }

    /**
     * Runs as its own DataHandler call, after the pages exist: the comment
     * table's foreign_uid column is a plain 'passthrough' TCA field (it can
     * point at any table, so it carries no relation config DataHandler could
     * resolve a "NEW-..." placeholder against), so the real page uid has to
     * be known already.
     */
    private function createDemoComment(int $pageUid, int $authorUid): void
    {
        $data = [
            Configuration::TABLE_COMMENT => [
                'NEW-comment' => [
                    'pid' => 0,
                    'foreign_table' => 'pages',
                    'foreign_uid' => $pageUid,
                    'content' => self::COMMENT_CONTENT,
                    'author' => $authorUid,
                ],
            ],
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();
        $this->assertNoErrors($dataHandler, 'creating the demo comment');
    }

    private function assertNoErrors(DataHandler $dataHandler, string $action): void
    {
        if ([] === $dataHandler->errorLog) {
            return;
        }

        throw new RuntimeException(sprintf('Seeding demo content failed while %s: %s', $action, implode('; ', $dataHandler->errorLog)));
    }
}
