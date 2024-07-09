<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Status extends AbstractEntity
{
    protected string $title = '';
    protected string $icon = '';
    protected string $color = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getColoredIcon(): string
    {
        return $this->icon . '-' . $this->color;
    }
}
