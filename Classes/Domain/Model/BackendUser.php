<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Domain\Model;

use TYPO3\CMS\Beuser\Domain\Model\BackendUser as User;

/**
 * BackendUser.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class BackendUser extends User
{
    protected bool $hide = false;
    protected string $subscribe = '';
    protected int $lastMail = 0;

    public function isHide(): bool
    {
        return $this->hide;
    }

    public function setHide(bool $hide): void
    {
        $this->hide = $hide;
    }

    public function getSubscribe(): string
    {
        return $this->subscribe;
    }

    public function setSubscribe(string $subscribe): void
    {
        $this->subscribe = $subscribe;
    }

    public function getLastMail(): int
    {
        return $this->lastMail;
    }

    public function setLastMail(int $lastMail): void
    {
        $this->lastMail = $lastMail;
    }
}
