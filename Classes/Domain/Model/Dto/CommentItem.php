<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\PermissionUtility;

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
        return $this->getRelatedRecord() ? $this->getRelatedRecord()['title'] : '';
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
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $status = ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);
        $icon = $iconFactory->getIcon($status ? $status->getColoredIcon() : 'flag-gray', 'small');
        return $icon->getIdentifier();
    }

    public function getRecordIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        if (!$this->getRelatedRecord()) {
            return '';
        }
        return $iconFactory->getIconForRecord($this->data['foreign_table'], $this->getRelatedRecord(), Icon::SIZE_SMALL)->getIdentifier();
    }

    public function getRecordLink(): string
    {
        switch ($this->data['foreign_table']) {
            case 'pages':
                return (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $this->data['foreign_uid']]);
            default:
                return (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', ['edit' => [$this->data['foreign_table'] => [$this->data['foreign_uid'] => 'edit']]]);
        }
    }

    public function getAuthorName(): ?string
    {
        return ContentUtility::getBackendUsernameById((int)$this->data['author']);
    }
}
