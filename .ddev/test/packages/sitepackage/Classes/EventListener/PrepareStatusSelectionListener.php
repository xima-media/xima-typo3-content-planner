<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Sitepackage\EventListener;

use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;

/**
 * PrepareStatusSelectionListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class PrepareStatusSelectionListener
{
    public function __construct(private readonly StatusRepository $statusRepository) {}

    public function __invoke(PrepareStatusSelectionEvent $event): void
    {
        $selection = $event->getSelection();

        if ($event->getCurrentStatus() && 'Needs review' === $event->getCurrentStatus()->getTitle()) {
            $targetStatus = $this->statusRepository->findOneByTitle('Pending');

            if ($targetStatus) {
                unset($selection[$targetStatus->getUid()]);
            }
        }

        $event->setSelection($selection);
    }
}
