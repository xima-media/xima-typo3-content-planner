<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

final class BulkUpdateCommand extends Command
{
    public function __construct(protected readonly StatusRepository $statusRepository)
    {
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
        $uid = (int)$input->getArgument('uid');
        $status = (int)$input->getArgument('status');
        $recursive = $input->getOption('recursive') !== false;
        $assignee = $input->getOption('assignee');
        $statusEntity = null;

        if ($status === 0) {
            $status = null;
        } else {
            $statusEntity = $this->statusRepository->findByUid($status);
            if ($statusEntity === null) {
                $output->writeln(sprintf('Status with uid %d not found.', $status));
                return Command::FAILURE;
            }
        }

        $count = 0;
        $uids = [$uid];

        if ($recursive && $table === 'pages') {
            $uids = array_merge($uids, $this->getSubpages($uid));
        }

        foreach ($uids as $uid) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $query = $queryBuilder
                ->update($table)
                ->set('tx_ximatypo3contentplanner_status', $status)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
                )
            ;
            if ($assignee !== null) {
                if ($assignee === 0) {
                    $assignee = null;
                }
                $query->set('tx_ximatypo3contentplanner_assignee', $assignee);
            }
            $query->executeStatement();

            $count++;
        }

        $output->writeln(sprintf('Updated %d "%s" records to status "%s".', $count, $table, ($statusEntity ? $statusEntity->getTitle() : 'clear')));

        return Command::SUCCESS;
    }

    private function getSubpages(int $pageId): array
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $subpages = $pageRepository->getMenu($pageId, 'uid');
        $subpageIds = $this->flattenArray($subpages);

        foreach ($subpageIds as $subpageId) {
            $subpageIds = array_merge($subpageIds, $this->getSubpages($subpageId));
        }

        return $subpageIds;
    }

    private function flattenArray(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function ($value, $key) use (&$result) {
            if ($key === 'uid' || is_int($value)) {
                $result[] = $value;
            }
        });
        return $result;
    }
}
