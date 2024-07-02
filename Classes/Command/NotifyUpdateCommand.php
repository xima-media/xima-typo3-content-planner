<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Beuser\Domain\Model\BackendUser;
use TYPO3\CMS\Beuser\Domain\Repository\BackendUserRepository;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentUpdateDataProvider;

final class NotifyUpdateCommand extends Command
{
    public function __construct(private readonly ContentUpdateDataProvider $contentUpdateDataProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('A command to notify users about relevant updates in the content planner.')
            ->addArgument(
                'period',
                InputArgument::OPTIONAL,
                'Get updates since this period (in seconds). Default is 1 day.',
                86400
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $period = $input->getArgument('period');
        $since = time() - $period;
        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);

        // ToDo: only regard users with access to the content planner
        foreach ($backendUserRepository->findAll() as $user) {
            $updates = $this->contentUpdateDataProvider->fetchUpdateData(true, $user->getUid(), $since);
            if (count($updates) > 0) {
                $output->writeln('User ' . $user->getUsername() . ' has ' . count($updates) . ' relevant updates.');
                $this->notify($user, $updates);
            }
        }

        return Command::SUCCESS;
    }

    private function notify(BackendUser $user, array $updates): void
    {
        if ($user->getEmail() === '') {
            return;
        }

        // https://www.derhansen.de/2022/06/using-typo3-fluidemail-in-cli-context.html
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $array = $siteFinder->getAllSites();
        $site = reset($array);

        $normalizedParams = new NormalizedParams(
            [
                'HTTP_HOST' => $site->getBase()->getHost(),
                'HTTPS' => $site->getBase()->getScheme() === 'https' ? 'on' : 'off',
            ],
            $systemConfiguration ?? $GLOBALS['TYPO3_CONF_VARS']['SYS'],
            '',
            ''
        );

        $request = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', $normalizedParams)
            ->withAttribute('site', $site);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $email = new FluidEmail();
        $email
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject('TYPO3 Content Planner Updates')
            ->format(FluidEmail::FORMAT_HTML)
            ->setTemplate('ContentUpdates')
            ->setRequest($request)
            ->assignMultiple(
                [
                    'data' => $updates,
                    'user' => $user,
                ]
            );
        GeneralUtility::makeInstance(MailerInterface::class)->send($email);
    }
}
