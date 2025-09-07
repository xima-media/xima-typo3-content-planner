<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

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
        private readonly CommentRepository $commentRepository
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
        if ($assignee !== null) {
            if ($assignee === 0) {
                $assignee = null;
            }
        }

        $count = 0;
        $uids = [$uid];

        if ($recursive && $table === 'pages') {
            $uids = [...$uids, ...$this->getSubpages($uid)];
        }

        foreach ($uids as $tempUid) {
            $this->recordRepository->updateStatusByUid($table, $tempUid, $status, $assignee);

            if ($status === null && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET)) {
                $this->commentRepository->deleteAllCommentsByRecord($tempUid, $table);
            }

            $count++;
        }

        $output->writeln(sprintf(
            'Updated %d "%s" records to status "%s".',
            $count,
            $table,
            $statusEntity !== null ? $statusEntity->getTitle() : 'clear'
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
    * @return int[]
    */
    private function flattenArray(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function (mixed $value, string|int $key) use (&$result): void {
            if ($key === 'uid' || is_int($value)) {
                $result[] = $value;
            }
        });
        return $result;
    }
}
