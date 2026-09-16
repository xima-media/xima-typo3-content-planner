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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService;

use function sprintf;

/**
 * ImmediateEmailFlushCommand.
 *
 * Re-checks every recipient/record pair still holding unsent immediate-email queue rows (issue
 * #306) and flushes the ones whose per-record throttle window has now elapsed and whose
 * recipient hourly cap has now reset. Without this, a batch queued because of either limit is
 * only ever re-examined by the next live event on that same record - which may never arrive,
 * leaving it queued indefinitely (see {@see ImmediateEmailService::flushDueQueues()}).
 *
 * Intended to be scheduled (e.g. every few minutes) via TYPO3 scheduler or an external cron,
 * alongside `content-planner:notification:digest` and `content-planner:notification:cleanup`.
 * Deliberately thin, following {@see NotificationCleanupCommand}'s convention: this class only
 * prints a summary, all actual work lives in {@see ImmediateEmailService}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsCommand(
    name: 'content-planner:notification:immediate-flush',
    description: 'Flushes immediate-email queue entries whose throttle window or hourly cap has since elapsed.',
)]
final class ImmediateEmailFlushCommand extends Command
{
    public function __construct(private readonly ImmediateEmailService $immediateEmailService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            'Re-checks every recipient/record pair with unsent immediate-email queue rows and '.
            'sends the ones that are now due, because the per-record throttle window has elapsed '.
            'or the recipient\'s hourly send cap has reset since they were queued. Rows still '.
            'inside either limit are left queued for a later run.',
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $flushed = $this->immediateEmailService->flushDueQueues();

        $output->writeln(sprintf('Flushed %d due immediate-email queue(s).', $flushed));

        return Command::SUCCESS;
    }
}
