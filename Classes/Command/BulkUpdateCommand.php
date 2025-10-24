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
 * @license GPL-2.0-or-later
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
        $recursive = false !== $input->getOption('recursive');

        $statusEntity = $this->resolveStatusEntity((int) $input->getArgument('status'), $output);
        if (null === $statusEntity && 0 !== (int) $input->getArgument('status')) {
            return Command::FAILURE;
        }

        $status = null !== $statusEntity ? $statusEntity->getUid() : null;

        // Validate and process assignee option
        $assigneeRawValue = $input->getOption('assignee');
        $assigneeOptionProvided = null !== $assigneeRawValue;

        if ($assigneeOptionProvided) {
            $validatedAssignee = $this->validateAssigneeValue($assigneeRawValue, $output);
            if (false === $validatedAssignee) {
                return Command::FAILURE;
            }
            $assignee = $this->normalizeAssignee($validatedAssignee, true);
        } else {
            $assignee = $this->normalizeAssignee(null, false);
        }

        $uids = $this->collectTargetUids($uid, $table, $recursive);
        $count = $this->performBulkUpdate($uids, $table, $status, $assignee);

        $output->writeln(sprintf(
            'Updated %d "%s" records to status "%s".',
            $count,
            $table,
            null !== $statusEntity ? $statusEntity->getTitle() : 'clear',
        ));

        return Command::SUCCESS;
    }

    private function resolveStatusEntity(int $status, OutputInterface $output): mixed
    {
        if (0 === $status) {
            return null;
        }

        $statusEntity = $this->statusRepository->findByUid($status);
        if (null === $statusEntity) {
            $output->writeln(sprintf('Status with uid %d not found.', $status));
        }

        return $statusEntity;
    }

    /**
     * Validate assignee value with strict integer check.
     *
     * @return int|false Returns validated integer or false on validation failure
     */
    private function validateAssigneeValue(mixed $value, OutputInterface $output): int|false
    {
        // Convert to string for validation
        $stringValue = (string) $value;

        // Strict integer validation using filter_var with explicit flags
        $validated = filter_var($stringValue, \FILTER_VALIDATE_INT, ['flags' => \FILTER_NULL_ON_FAILURE]);

        if (null === $validated) {
            $output->writeln(sprintf(
                '<error>Invalid assignee value "%s". Must be a valid integer (e.g., 0, 1, 123).</error>',
                $stringValue,
            ));

            return false;
        }

        return $validated;
    }

    /**
     * Normalize validated assignee value.
     *
     * @param int|null $assignee Already validated integer or null for omitted option
     *
     * @return int|false|null Returns false when option was not provided (unchanged),
     *                        null when explicitly clearing (0),
     *                        or the integer user ID
     */
    private function normalizeAssignee(?int $assignee, bool $optionProvided): int|false|null
    {
        // Option was not provided at all - return sentinel to indicate "unchanged"
        if (!$optionProvided) {
            return false;
        }

        // Option was provided with value 0 - explicitly clear assignee
        if (0 === $assignee) {
            return null;
        }

        // Option was provided with a valid user ID
        return $assignee;
    }

    /**
     * @return int[]
     */
    private function collectTargetUids(int $uid, string $table, bool $recursive): array
    {
        $uids = [$uid];

        if ($recursive && 'pages' === $table) {
            $uids = [...$uids, ...$this->getSubpages($uid)];
        }

        return $uids;
    }

    /**
     * @param int[]          $uids
     * @param int|false|null $assignee false means unchanged, null means clear, int means set to user ID
     */
    private function performBulkUpdate(array $uids, string $table, ?int $status, int|false|null $assignee): int
    {
        $count = 0;

        foreach ($uids as $uid) {
            $this->recordRepository->updateStatusByUid($table, $uid, $status, $assignee);

            if (null === $status && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET)) {
                $this->commentRepository->deleteAllCommentsByRecord($uid, $table);
            }

            ++$count;
        }

        return $count;
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
