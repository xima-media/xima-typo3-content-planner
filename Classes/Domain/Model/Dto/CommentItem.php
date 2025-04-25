<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;
use Xima\XimaTypo3ContentPlanner\Utility\PermissionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

final class CommentItem
{
    public array $data = [];
    public array|bool $relatedRecord = [];
    public ?Status $status = null;

    public static function create(array $row): static
    {
        $item = new CommentItem();
        $item->data = $row;

        return $item;
    }

    public function getTitle(): string
    {
        return ExtensionUtility::getTitle(ExtensionUtility::getTitleField($this->data['foreign_table']), $this->getRelatedRecord());
    }

    public function getRelatedRecord(): array|bool
    {
        if (empty($this->relatedRecord)) {
            $this->relatedRecord = ContentUtility::getExtensionRecord($this->data['foreign_table'], (int)$this->data['foreign_uid']);
        }

        if (!PermissionUtility::checkAccessForRecord($this->data['foreign_table'], $this->relatedRecord)) {
            $this->relatedRecord = false;
        }

        return $this->relatedRecord;
    }

    public function getStatusIcon(): string
    {
        return IconHelper::getIconByStatusUid((int)$this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);
    }

    public function getRecordIcon(): string
    {
        return IconHelper::getIconByRecord($this->data['foreign_table'], $this->getRelatedRecord());
    }

    public function getRecordLink(): string
    {
        return UrlHelper::getRecordLink($this->data['foreign_table'], (int)$this->data['foreign_uid']);
    }

    public function getAuthorName(): string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['author']);
    }

    public function getEditUri(): string
    {
        return UrlHelper::getEditCommentUrl((int)$this->data['uid']);
    }

    public function getResolvedUri(): string
    {
        return UrlHelper::getResolvedCommentUrl((int)$this->data['uid'], $this->isResolved());
    }

    public function getDeleteUri(): string
    {
        return UrlHelper::getDeleteCommentUrl((int)$this->data['uid']);
    }

    public function isResolved(): bool
    {
        return $this->data['resolved'] !== '';
    }

    public function getResolvedUser(): string
    {
        if (!$this->isResolved()) {
            return '';
        }

        $resolvedData = json_decode($this->data['resolved'], true);
        if (!is_array($resolvedData) || !isset($resolvedData['user'])) {
            return '';
        }

        return ContentUtility::getBackendUsernameById((int)$resolvedData['user']);
    }

    public function getResolvedDate(): int
    {
        if (!$this->isResolved()) {
            return 0;
        }

        $resolvedData = json_decode($this->data['resolved'], true);
        if (!is_array($resolvedData) || !isset($resolvedData['date'])) {
            return 0;
        }

        return (int)$resolvedData['date'];
    }
}
