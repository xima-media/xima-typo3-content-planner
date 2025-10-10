<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function is_int;
use function sprintf;

/**
 * BulkUpdateCommand.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class BulkUpdateCommand extends Command
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('content-planner:bulk-update')
            ->addArgument('table', InputArgument::OPTIONAL, 'The table to update.', 'pages')
            ->addArgument('uid', InputArgument::OPTIONAL, 'The uid to update.', 1)
            ->addArgument('status', InputArgument::OPTIONAL, 'The status uid to set. If empty, the status will be cleared.', null)
            ->addOption('recursive', 'r', InputOption::VALUE_OPTIONAL, 'Whether to update pages recursively.', false)
            ->addOption('assignee', 'a', InputOption::VALUE_REQUIRED, 'The backend user uid to set an assignee for this record.', null)
            ->addUsage('pages 1 4')
            ->setHelp('A command to perform a bulk operation to content planner entities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getArgument('table');
        $uid = (int) $input->getArgument('uid');
        $status = (int) $input->getArgument('status');
        $recursive = false !== $input->getOption('recursive');
        $assignee = $input->getOption('assignee');
        $statusEntity = null;

        if (0 === $status) {
            $status = null;
        } else {
            $statusEntity = $this->statusRepository->findByUid($status);
            if (null === $statusEntity) {
                $output->writeln(sprintf('Status with uid %d not found.', $status));

                return Command::FAILURE;
            }
        }
        if (null !== $assignee) {
            if (0 === $assignee) {
                $assignee = null;
            }
        }

        $count = 0;
        $uids = [$uid];

        if ($recursive && 'pages' === $table) {
            $uids = [...$uids, ...$this->getSubpages($uid)];
        }

        foreach ($uids as $tempUid) {
            $this->recordRepository->updateStatusByUid($table, $tempUid, $status, $assignee);

            if (null === $status && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET)) {
                $this->commentRepository->deleteAllCommentsByRecord($tempUid, $table);
            }

            ++$count;
        }

        $output->writeln(sprintf(
            'Updated %d "%s" records to status "%s".',
            $count,
            $table,
            null !== $statusEntity ? $statusEntity->getTitle() : 'clear',
        ));

        return Command::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function getSubpages(int $pageId): array
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $subpages = $pageRepository->getMenu($pageId, 'uid');
        $subpageIds = $this->flattenArray($subpages);

        foreach ($subpageIds as $subpageId) {
            $subpageIds = [...$subpageIds, ...$this->getSubpages($subpageId)];
        }

        return $subpageIds;
    }

    /**
     * @param array<int|string, mixed> $array
     *
     * @return int[]
     */
    private function flattenArray(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function (mixed $value, string|int $key) use (&$result): void {
            if ('uid' === $key || is_int($value)) {
                $result[] = $value;
            }
        });

        return $result;
    }
}
