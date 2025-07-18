<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\DiffUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;
use Xima\XimaTypo3ContentPlanner\Utility\PermissionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

final class CommentItem
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|bool */
    public array|bool $relatedRecord = [];

    public ?Status $status = null;

    /**
    * @param array<string, mixed> $row
    * @return static
    */
    public static function create(array $row): static
    {
        $item = new self();
        $item->data = $row;

        return $item;
    }

    public function getTitle(): string
    {
        return ExtensionUtility::getTitle(ExtensionUtility::getTitleField($this->data['foreign_table']), $this->getRelatedRecord());
    }

    /**
    * @return array<string, mixed>|bool
    */
    public function getRelatedRecord(): array|bool
    {
        if ($this->relatedRecord === [] || $this->relatedRecord === false) {
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

    public function getTimeAgo(): string
    {
        return DiffUtility::timeAgo($this->data['crdate']);
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

    public function isEdited(): bool
    {
        return (bool)$this->data['edited'];
    }

    public function isResolved(): bool
    {
        return $this->data['resolved_date'] > 0;
    }

    public function getResolvedUser(): string
    {
        if (!$this->isResolved()) {
            return '';
        }

        return ContentUtility::getBackendUsernameById((int)$this->data['resolved_user']);
    }

    public function getResolvedDate(): int
    {
        return (int)$this->data['resolved_date'];
    }
}
