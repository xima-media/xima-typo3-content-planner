<?php

namespace Xima\XimaContentPlanner\Domain\Model\Dto;

use Xima\XimaContentPlanner\Configuration;
use Xima\XimaContentPlanner\Utility\ContentUtility;

class StatusItem
{
    public array $data = [];

    public static function create(array $pageRow): static
    {
        $item = new static();
        $item->data = $pageRow;

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        return ((int)$this->data['tx_ximacontentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getPageTitle(): ?string
    {
        return $this->data['title'];
    }

    public function getPageStatus(): ?string
    {
        return $this->data['tx_ximacontentplanner_status'];
    }

    public function getPageStatusIcon(): string
    {
        return Configuration::STATUS_ICONS[$this->data['tx_ximacontentplanner_status']];
    }

    public function getPageAssignee(): ?string
    {
        return $this->data['tx_ximacontentplanner_assignee'];
    }

    public function getPageAssigneeName(): ?string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['tx_ximacontentplanner_assignee']);
    }
}
