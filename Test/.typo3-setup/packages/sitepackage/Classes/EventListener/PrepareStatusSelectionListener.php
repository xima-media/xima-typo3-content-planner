<?php

declare(strict_types=1);

namespace Test\Sitepackage\EventListener;

use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;

final class PrepareStatusSelectionListener
{

    public function __construct(private readonly StatusRepository $statusRepository)
    {
    }

    public function __invoke(PrepareStatusSelectionEvent $event): void
    {
        $selection = $event->getSelection();

        if ($event->getCurrentStatus() && $event->getCurrentStatus()->getTitle() === 'Needs review') {
            $targetStatus = $this->statusRepository->findOneByTitle('Pending');

            if ($targetStatus) {
                unset($selection[$targetStatus->getUid()]);
            }
        }

        $event->setSelection($selection);
    }
}
