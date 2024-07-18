<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\BackendUser;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentUpdateDataProvider;

final class NotifyUpdateCommand extends Command
{
    public const SUBSCRIBE_FREQUENCY = [
        'daily' => 86400,
        'weekly' => 604800,
    ];

    public function __construct(private readonly ContentUpdateDataProvider $contentUpdateDataProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('A command to notify users about relevant updates in the content planner.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);

        // ToDo: only regard users with access to the content planner
        foreach ($backendUserRepository->findAll() as $user) {
            if ($user->getEmail() === '' || !(bool)$user->getSubscribe() || ($user->getLastMail() + self::SUBSCRIBE_FREQUENCY[$user->getSubscribe()]) > time()) {
                continue;
            }
            $output->writeln('Checking updates for user ' . $user->getUsername() . ' ...');

            $since = time() - (int)self::SUBSCRIBE_FREQUENCY[$user->getSubscribe()];

            $updates = $this->contentUpdateDataProvider->fetchUpdateData(false, null, $since, null, true);
            if (count($updates) > 0) {
                $output->writeln('User ' . $user->getUsername() . ' has ' . count($updates) . ' updates.');
                $result = $this->notify($user, $updates);

                if ($result) {
                    $user->setLastMail(time());
                    $backendUserRepository->update($user);
                    // ToDo: why isn't the user updated?
                }
            }
        }

        return Command::SUCCESS;
    }

    private function notify(BackendUser $user, array $updates): bool
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $array = $siteFinder->getAllSites();
        $site = reset($array);

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        /** @var StandaloneView $view */
        $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Email/ContentUpdates.html');

        $view->setTemplateRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Partials/']);
        $view->setLayoutRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Layouts/']);

        $view->assignMultiple(
            [
                'data' => $updates,
                'user' => $user,
                'backendUrl' => $site->getBase()->__toString() . '/typo3',
            ]
        );

        $email = new MailMessage();
        $email
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject('TYPO3 Content Planner Updates')
            ->html(
                // workaround: to resolve the base url in the email
                str_replace('href="', 'href="' . $site->getBase()->__toString() . '/', $view->render())
            );
        GeneralUtility::makeInstance(MailerInterface::class)->send($email);

        return true;
    }
}
