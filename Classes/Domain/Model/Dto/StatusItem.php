<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

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
        return ((int)$this->data['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getPageTitle(): ?string
    {
        return $this->data['title'];
    }

    public function getPageStatus(): ?string
    {
        return $this->data['tx_ximatypo3contentplanner_status'];
    }

    public function getPageStatusIcon(): string
    {
        return Configuration::STATUS_ICONS[$this->data['tx_ximatypo3contentplanner_status']];
    }

    public function getPageAssignee(): ?string
    {
        return $this->data['tx_ximatypo3contentplanner_assignee'];
    }

    public function getPageAssigneeName(): ?string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['tx_ximatypo3contentplanner_assignee']);
    }
}
