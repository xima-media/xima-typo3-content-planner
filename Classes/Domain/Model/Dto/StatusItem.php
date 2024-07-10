<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Backend\Backend\Avatar\Avatar;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon($this->status->getColoredIcon(), 'small');
        return $icon->render();
    }

    public function getPageAssignee(): ?string
    {
        return $this->data['tx_ximatypo3contentplanner_assignee'];
    }

    public function getPageAssigneeName(): ?string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['tx_ximatypo3contentplanner_assignee']);
    }

    public function getPageAssigneeAvatar(): ?string
    {
        $backendUser = ContentUtility::getBackendUserById((int)$this->data['tx_ximatypo3contentplanner_assignee']);
        $avatar = GeneralUtility::makeInstance(Avatar::class);
        return $avatar->render($backendUser, 15, true);
    }

    public function getPageComments(): ?string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon('content-message', 'small');
        return $this->data['tx_ximatypo3contentplanner_comments'] . ' ' . $icon->render();
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'pageId' => $this->data['uid'],
            'pageLink' => (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $this->data['uid']]),
            'pageTitle' => $this->getPageTitle(),
            'status' => $this->getPageStatus(),
            'statusIcon' => $this->getPageStatusIcon(),
            'updated' => (new \DateTime())->setTimestamp($this->data['tstamp'])->format('d.m.Y H:i'),
            'assignee' => $this->getPageAssignee(),
            'assigneeName' => $this->getPageAssigneeName(),
            'assigneeAvatar' => $this->getPageAssigneeAvatar(),
            'assignedToCurrentUser' => $this->getAssignedToCurrentUser(),
            'comments' => $this->getPageComments(),
        ];
    }
}
