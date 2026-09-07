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

namespace Xima\XimaTypo3ContentPlanner\Event;

/**
 * AssigneeChangedEvent.
 *
 * Dispatched from {@see \Xima\XimaTypo3ContentPlanner\Manager\StatusChangeManager::processContentPlannerFields()}
 * whenever the assignee field actually changes, including as a side effect of auto-assignment
 * ({@see \Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility} feature `autoAssignment`).
 *
 * Only dispatched when the incoming field array touches the assignee field at all - a request
 * that never sets the status field is not processed by `processContentPlannerFields()` in the
 * first place (see its early return), so a pure assignee-only edit unrelated to any status
 * change still emits nothing. That pre-existing gap is out of scope for this event.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class AssigneeChangedEvent
{
    public function __construct(
        private string $table,
        private int $uid,
        private ?int $previousAssignee,
        private ?int $newAssignee,
        private ?int $actorUid,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getPreviousAssignee(): ?int
    {
        return $this->previousAssignee;
    }

    public function getNewAssignee(): ?int
    {
        return $this->newAssignee;
    }

    /**
     * UID of the backend user who triggered the change, or null when unavailable (e.g. CLI
     * context without an authenticated backend user).
     */
    public function getActorUid(): ?int
    {
        return $this->actorUid;
    }
}
