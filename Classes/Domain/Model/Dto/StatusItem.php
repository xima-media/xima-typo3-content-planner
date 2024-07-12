<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Backend\Backend\Avatar\Avatar;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class StatusItem
{
    public array $data = [];
    public ?Status $status = null;

    public static function create(array $row): static
    {
        $item = new static();
        $item->data = $row;
        $item->status = ContentUtility::getStatus($row['tx_ximatypo3contentplanner_status']);

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        return ((int)$this->data['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getTitle(): ?string
    {
        return $this->data['title'];
    }

    public function getStatus(): ?string
    {
        return $this->status ? $this->status->getTitle() : null;
    }

    public function getStatusIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon($this->status ? $this->status->getColoredIcon() : 'flag-gray', 'small');
        return $icon->render();
    }

    public function getRecordIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        return $iconFactory->getIconForRecord($this->data['tablename'], $this->data, Icon::SIZE_SMALL)->render();
    }

    public function getRecordLink(): string
    {
        switch ($this->data['tablename']) {
            case 'pages':
                return (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $this->data['uid']]);
            default:
                return (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', ['edit' => [$this->data['tablename'] => [$this->data['uid'] => 'edit']]]);
        }
    }

    public function getAssignee(): ?string
    {
        return $this->data['tx_ximatypo3contentplanner_assignee'];
    }

    public function getAssigneeName(): ?string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['tx_ximatypo3contentplanner_assignee']);
    }

    public function getAssigneeAvatar(): ?string
    {
        $backendUser = ContentUtility::getBackendUserById((int)$this->data['tx_ximatypo3contentplanner_assignee']);
        if (!$backendUser) {
            return null;
        }
        $avatar = GeneralUtility::makeInstance(Avatar::class);
        return $avatar->render($backendUser, 15, true);
    }

    public function getComments(): ?string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon('content-message', 'small');
        return $this->data['tx_ximatypo3contentplanner_comments'] . ' ' . $icon->render();
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'link' => $this->getRecordLink(),
            'title' => $this->getTitle(),
            'status' => $this->getStatus(),
            'statusIcon' => $this->getStatusIcon(),
            'recordIcon' => $this->getRecordIcon(),
            'updated' => (new \DateTime())->setTimestamp($this->data['tstamp'])->format('d.m.Y H:i'),
            'assignee' => $this->getAssignee(),
            'assigneeName' => $this->getAssigneeName(),
            'assigneeAvatar' => $this->getAssigneeAvatar(),
            'assignedToCurrentUser' => $this->getAssignedToCurrentUser(),
            'comments' => $this->getComments(),
        ];
    }
}
