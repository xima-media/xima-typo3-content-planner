<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Manager;

use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class StatusSelectionManager
{
    public function __construct(private readonly EventDispatcher $eventDispatcher)
    {
    }

    public function prepareStatusSelection(object $context, string $table, ?int $uid, array &$selection, ?int $statusUid = null, ?Status $status = null): void
    {
        if ($statusUid !== null && $status === null) {
            $status = ContentUtility::getStatus($statusUid);
        }

        $event = $this->eventDispatcher->dispatch(new PrepareStatusSelectionEvent($table, $uid, $context, $selection, $status));
        $selection = $event->getSelection();
    }
}
