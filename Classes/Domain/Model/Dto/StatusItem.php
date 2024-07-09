<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class StatusItem
{
    public array $data = [];
    public ?Status $status = null;

    public static function create(array $pageRow): static
    {
        $item = new static();
        $item->data = $pageRow;
        $item->status = ContentUtility::getStatus($pageRow['tx_ximatypo3contentplanner_status']);

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
        return $this->status->getTitle();
    }

    public function getPageStatusIcon(): string
    {
        return $this->status->getColoredIcon();
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
