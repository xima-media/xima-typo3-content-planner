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

namespace Xima\XimaTypo3ContentPlanner\Manager;

use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

/**
 * StatusSelectionManager.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class StatusSelectionManager
{
    public function __construct(private readonly EventDispatcher $eventDispatcher) {}

    /**
     * @param array<string, mixed> $selection
     */
    public function prepareStatusSelection(object $context, string $table, ?int $uid, array &$selection, ?int $statusUid = null, ?Status $status = null): void
    {
        if (null !== $statusUid && null === $status) {
            $status = ContentUtility::getStatus($statusUid);
        }

        $event = $this->eventDispatcher->dispatch(new PrepareStatusSelectionEvent($table, $uid, $context, $selection, $status));
        $selection = $event->getSelection();
    }
}
