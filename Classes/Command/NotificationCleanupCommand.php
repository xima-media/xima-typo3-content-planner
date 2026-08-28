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

namespace Xima\XimaTypo3ContentPlanner\Command;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\RetentionRunResult;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Retention\NotificationRetentionService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function sprintf;

/**
 * NotificationCleanupCommand.
 *
 * Retention and orphan cleanup for the notification feature (issue #304) - intended to be
 * scheduled (e.g. daily, alongside `content-planner:notification:digest`) via TYPO3 scheduler or
 * an external cron. See Documentation/DeveloperCorner/Notifications.rst for the full retention
 * rule contract.
 *
 * Deliberately thin, following {@see EmailDigestCommand}'s
 * convention: this class only resolves the configured thresholds and prints a summary, all actual
 * work (querying, chunked deleting) lives in {@see NotificationRetentionService}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsCommand(
    name: 'content-planner:notification:cleanup',
    description: 'Deletes old and orphaned content planner notifications and watchers per the configured retention rules.',
)]
final class NotificationCleanupCommand extends Command
{
    public function __construct(private readonly NotificationRetentionService $retentionService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Print counts of what would be deleted, without deleting anything.',
            )
            ->setHelp(
                'Applies four retention/orphan rules in one run:'."\n\n".
                '  - deletes read notifications older than the configured "notificationRetentionReadDays"'."\n".
                '  - deletes unread notifications older than the configured "notificationRetentionUnreadDays"'."\n".
                '  - deletes watcher/notification rows whose referenced record no longer exists'."\n".
                '  - deletes watcher/notification rows owned by a deleted/disabled backend user'."\n\n".
                'Both thresholds are configured via this extension\'s extension configuration '.
                '(defaults: 30 / 90 days). Deletes run in fixed-size batches so the command stays '.
                'safe to run against a large notification table.',
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $readRetentionDays = ExtensionUtility::getNotificationRetentionReadDays();
        $unreadRetentionDays = ExtensionUtility::getNotificationRetentionUnreadDays();

        $result = $this->retentionService->run($readRetentionDays, $unreadRetentionDays, $dryRun);

        $this->writeSummary($output, $result, $readRetentionDays, $unreadRetentionDays);

        return Command::SUCCESS;
    }

    private function writeSummary(OutputInterface $output, RetentionRunResult $result, int $readRetentionDays, int $unreadRetentionDays): void
    {
        $verb = $result->isDryRun() ? 'Would delete' : 'Deleted';

        $output->writeln(sprintf('%s %d read notification(s) older than %d day(s).', $verb, $result->getReadNotificationsDeleted(), $readRetentionDays));
        $output->writeln(sprintf('%s %d unread notification(s) older than %d day(s).', $verb, $result->getUnreadNotificationsDeleted(), $unreadRetentionDays));
        $output->writeln(sprintf('%s %d orphaned notification(s).', $verb, $result->getOrphanedNotificationsDeleted()));
        $output->writeln(sprintf('%s %d orphaned watcher(s).', $verb, $result->getOrphanedWatchersDeleted()));
        $output->writeln(sprintf('%s %d row(s) in total.', $verb, $result->getTotalDeleted()));
    }
}
