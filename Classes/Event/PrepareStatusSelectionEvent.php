<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Event;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

/**
* PrepareStatusSelectionEvent
*
* Use this event to influence the status selection in the backend regarding multiple factors (e.g. current state, current record or context class).
* Therefore, it is possible to implement some kind of simple workflow for status changes.
*
* Example usage:
*```php
* $selection = $event->getSelection();
*
* if ($event->getCurrentStatus() && $event->getCurrentStatus()->getTitle() === 'Needs review') {
*  $targetStatus = $this->statusRepository->findOneByTitle('Pending');
*
*  if ($targetStatus) {
*      unset($selection[$targetStatus->getUid()]);
*  }
* }
*
* $event->setSelection($selection);
* ```
*/
class PrepareStatusSelectionEvent
{
    final public const NAME = 'xima_typo3_content_planner.status.prepare_selection';

    public function __construct(
        protected string $table,
        protected int $uid,
        protected object $context,
        protected array $selection,
        protected ?Status $currentStatus,
    ) {
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getSelection(): array
    {
        return $this->selection;
    }

    public function setSelection(array $selection): void
    {
        $this->selection = $selection;
    }

    /**
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public function getCurrentStatus(): ?Status
    {
        return $this->currentStatus;
    }

    public function getContext(): object
    {
        return $this->context;
    }
}
