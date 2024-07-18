<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Model;

use TYPO3\CMS\Beuser\Domain\Model\BackendUser as User;

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
