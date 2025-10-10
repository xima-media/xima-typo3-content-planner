<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
