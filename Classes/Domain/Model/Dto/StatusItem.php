<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

final class StatusItem
{
    public array $data = [];
    public ?Status $status = null;

    public static function create(array $row): static
    {
        $item = new StatusItem();
        $item->data = $row;
        $item->status = ContentUtility::getStatus($row['tx_ximatypo3contentplanner_status']);

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
            return false;
        }
        return ((int)$this->data['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getTitle(): ?string
    {
        return ExtensionUtility::getTitle('title', $this->data);
    }

    public function getStatus(): ?string
    {
        return $this->status?->getTitle();
    }

    public function getStatusIcon(): string
    {
        return IconHelper::getIconByStatus($this->status, true);
    }

    public function getRecordIcon(): string
    {
        return IconHelper::getIconByRecord($this->data['tablename'], $this->data, true);
    }

    public function getRecordLink(): string
    {
        return UrlHelper::getRecordLink($this->data['tablename'], $this->data['uid']);
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
        return IconHelper::getAvatarByUserId((int)$this->data['tx_ximatypo3contentplanner_assignee']);
    }

    public function getComments(): ?string
    {
        return $this->data['tx_ximatypo3contentplanner_comments'] . ' ' . IconHelper::getIconByIdentifier('actions-message');
    }

    public function getSite(): ?string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        if (count($siteFinder->getAllSites()) <= 1) {
            return null;
        }
        $site = $siteFinder->getSiteByPageId($this->data['tablename'] === 'pages' ? $this->data['uid'] : $this->data['pid']);
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon('apps-pagetree-folder-root', 'small');
        return $icon->render() . ' ' . ($site->getAttribute('websiteTitle') ?: $site->getIdentifier());
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
            'site' => $this->getSite(),
        ];
    }
}
