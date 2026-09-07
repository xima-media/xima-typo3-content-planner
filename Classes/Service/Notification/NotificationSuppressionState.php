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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

/**
 * NotificationSuppressionState.
 *
 * Process-wide pause switch for {@see NotificationDispatcher}, e.g. for a CLI migration script
 * that touches many records and must not produce a notification storm. Registered as a shared
 * (singleton) service, so every collaborator within one request/CLI process sees the same state.
 *
 * See issue #300's "Bulk/CLI semantics" acceptance criterion and
 * Documentation/DeveloperCorner/Notifications.rst for the full reasoning, including why
 * {@see \Xima\XimaTypo3ContentPlanner\Command\BulkUpdateCommand}'s `--no-notify` option is
 * currently a defensive no-op.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationSuppressionState
{
    private bool $paused = false;

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }
}
