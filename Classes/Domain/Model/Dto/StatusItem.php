<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

final class StatusItem
{
    public array $data = [];
    public ?Status $status = null;
    private ?CommentRepository $commentRepository = null;

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

    public function getTitle(): string
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

    public function getAssigneeName(): string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['tx_ximatypo3contentplanner_assignee']);
    }

    public function getAssigneeAvatar(): string
    {
        return IconHelper::getAvatarByUserId((int)$this->data['tx_ximatypo3contentplanner_assignee']);
    }

    public function getCommentsHtml(): string
    {
        return $this->data['tx_ximatypo3contentplanner_comments'] ? sprintf(
            '%s <span class="badge">%d</span>',
            IconHelper::getIconByIdentifier('actions-message'),
            $this->data['tx_ximatypo3contentplanner_comments']
        ) : '';
    }

    public function getSite(): ?string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        if (count($siteFinder->getAllSites()) <= 1) {
            return null;
        }
        $site = $siteFinder->getSiteByPageId($this->data['tablename'] === 'pages' ? $this->data['uid'] : $this->data['pid']);
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon('apps-pagetree-folder-root', IconHelper::getDefaultIconSize());
        return $icon->render() . ' ' . ($site->getAttribute('websiteTitle') ?: $site->getIdentifier());
    }

    public function getToDoHtml(): string
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return '';
        }
        return $this->getToDoTotal() ? sprintf(
            '%s <span class="xima-typo3-content-planner--comment-todo badge" data-status="%s">%d/%d</span>',
            IconHelper::getIconByIdentifier('actions-check-square'),
            $this->getToDoResolved() === $this->getToDoTotal() ? 'resolved' : 'pending',
            $this->getToDoResolved(),
            $this->getToDoTotal()
        ) : '';
    }

    public function getToDoResolved(): int
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return 0;
        }
        return $this->data['tx_ximatypo3contentplanner_comments'] ? $this->getCommentRepository()->countTodoAllByRecord($this->data['uid'], $this->data['tablename']) : 0;
    }

    public function getToDoTotal(): int
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return 0;
        }
        return $this->data['tx_ximatypo3contentplanner_comments'] ? $this->getCommentRepository()->countTodoAllByRecord($this->data['uid'], $this->data['tablename'], 'todo_total') : 0;
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
            'comments' => $this->getCommentsHtml(),
            'todo' => $this->getToDoHtml(),
            'site' => $this->getSite(),
        ];
    }

    private function getCommentRepository(): CommentRepository
    {
        if ($this->commentRepository === null) {
            $this->commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        }
        return $this->commentRepository;
    }
}
