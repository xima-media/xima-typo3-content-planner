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
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\DigestRunResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function sprintf;

/**
 * EmailDigestCommand.
 *
 * Periodic email digest (issue #302): one mail per recipient at most, summarizing every
 * non-digested notification of theirs, deduped per record by {@see DigestService}. Intended to
 * be scheduled (e.g. daily) via TYPO3 scheduler or an external cron - see
 * Documentation/DeveloperCorner/Notifications.rst for the full dedup/opt-out contract.
 *
 * Deliberately thin, following {@see BulkUpdateCommand}'s
 * convention: this class only resolves the recipient list and prints a summary, all actual work
 * (validation, grouping, sending, marking digested) lives in {@see DigestService}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsCommand(
    name: 'content-planner:notification:digest',
    description: 'Sends one digest email per recipient, summarizing their pending content planner notifications.',
)]
final class EmailDigestCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly DigestService $digestService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Print a per-recipient summary only - sends no mail and marks nothing as digested.',
            )
            ->setHelp(
                'Collects every non-digested notification per recipient (read or unread - reading a '.
                'notification in the backend does not remove it from the digest), groups them by '.
                'record, and sends at most one summary email per recipient. Safe to re-run: only '.
                'notifications actually rendered into a sent mail are marked digested. Respects '.
                'the per-user "Receive content planner email digest" opt-out and the '.
                '"notificationDigestEmail" extension configuration toggle.',
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!ExtensionUtility::isNotificationDigestEmailEnabled()) {
            $output->writeln('The email digest channel is disabled (see extension configuration). Nothing to do.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $recipients = $this->notificationRepository->findRecipientsWithPendingDigest();

        if ([] === $recipients) {
            $output->writeln('Nothing to digest.');

            return Command::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($recipients as $recipientUid) {
            $result = $this->safelyProcessRecipient($output, $recipientUid, $dryRun);

            if (null === $result) {
                ++$failed;
                continue;
            }

            if ($result->wasSkipped()) {
                ++$skipped;
                $output->writeln(
                    sprintf('Recipient %d: skipped (%s).', $recipientUid, (string) $result->getSkipReason()),
                    OutputInterface::VERBOSITY_VERBOSE,
                );
                continue;
            }

            ++$sent;
            $this->writeRecipientLine($output, $recipientUid, $result, $dryRun);
        }

        $output->writeln(sprintf(
            '%s %d recipient(s), skipped %d, failed %d.',
            $dryRun ? 'Would send to' : 'Sent to',
            $sent,
            $skipped,
            $failed,
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * One recipient's transport failure (e.g. the mail server being unreachable) must not abort
     * the whole run - every other recipient still gets their digest, and the failed one simply
     * stays non-digested for the next run to retry.
     */
    private function safelyProcessRecipient(OutputInterface $output, int $recipientUid, bool $dryRun): ?DigestRunResult
    {
        try {
            return $this->digestService->processRecipient($recipientUid, $dryRun);
        } catch (Throwable $exception) {
            $this->logger?->error('Content planner email digest failed for one recipient', [
                'backendUser' => $recipientUid,
                'exception' => $exception,
            ]);
            $output->writeln(sprintf('Recipient %d: failed (%s).', $recipientUid, $exception->getMessage()));

            return null;
        }
    }

    private function writeRecipientLine(OutputInterface $output, int $recipientUid, DigestRunResult $result, bool $dryRun): void
    {
        $output->writeln(sprintf(
            '%s recipient %d: %d notification(s) across %d record(s).',
            $dryRun ? 'Would notify' : 'Notified',
            $recipientUid,
            $result->getNotificationCount(),
            $result->getGroupCount(),
        ));
    }
}
