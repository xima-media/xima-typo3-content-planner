<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Event;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

class StatusChangeEvent
{
    final public const NAME = 'xima_typo3_content_planner.status.change';

    public function __construct(
        protected string $table,
        protected int $uid,
        protected array $fieldArray,
        protected ?Status $previousStatus,
        protected ?Status $newStatus
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

    public function getFieldArray(): array
    {
        return $this->fieldArray;
    }

    public function setFieldArray(array $fieldArray): void
    {
        $this->fieldArray = $fieldArray;
    }

    /**
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public function getPreviousStatus(): ?Status
    {
        return $this->previousStatus;
    }

    /**
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public function getNewStatus(): ?Status
    {
        return $this->newStatus;
    }

    /**
    * @param \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null $newStatus
    */
    public function setNewStatus(?Status $newStatus): void
    {
        $this->newStatus = $newStatus;
    }
}
