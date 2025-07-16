<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

interface SelectionInterface
{
    /**
    * @param array<string|int, mixed> $selectionEntriesToAdd
    * @param array<int>|int|null $uid
    * @param array<string, mixed>|bool|null $record
    */
    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<int,int>|int|null $uid
    * @param array<string, mixed>|bool|null $record
    */
    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void;
}
