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
}
