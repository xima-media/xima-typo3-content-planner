<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\Data\{ContentUtility, DiffUtility};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;

/**
 * CommentItem.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentItem
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|bool */
    public array|bool $relatedRecord = [];

    public ?Status $status = null;

    /**
     * @param array<string, mixed> $row
     */
    public static function create(array $row): static
    {
        $item = new self();
        $item->data = $row;

        return $item;
    }

    public function getTitle(): string
    {
        // Special handling for sys_file_metadata - get filename from sys_file
        if ('sys_file_metadata' === $this->data['foreign_table']) {
            $record = $this->getRelatedRecord();
            if (is_array($record) && isset($record['file'])) {
                $fileRecord = BackendUtility::getRecord('sys_file', (int) $record['file'], 'name');
                if (is_array($fileRecord) && isset($fileRecord['name'])) {
                    return $fileRecord['name'];
                }
            }

            return BackendUtility::getNoRecordTitle();
        }

        return ExtensionUtility::getTitle(ExtensionUtility::getTitleField($this->data['foreign_table']), $this->getRelatedRecord());
    }

    /**
     * @return array<string, mixed>|bool
     */
    public function getRelatedRecord(): array|bool
    {
        if ([] === $this->relatedRecord || false === $this->relatedRecord) {
            $this->relatedRecord = ContentUtility::getExtensionRecord($this->data['foreign_table'], (int) $this->data['foreign_uid']);
        }

        if (!PermissionUtility::checkAccessForRecord($this->data['foreign_table'], $this->relatedRecord)) {
            $this->relatedRecord = false;
        }

        return $this->relatedRecord;
    }

    public function getStatusIcon(): string
    {
        return IconUtility::getIconByStatusUid((int) $this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);
    }

    public function getRecordIcon(): string
    {
        return IconUtility::getIconByRecord($this->data['foreign_table'], $this->getRelatedRecord());
    }

    public function getRecordLink(): string
    {
        return UrlUtility::getRecordLink($this->data['foreign_table'], (int) $this->data['foreign_uid']);
    }

    public function getAuthorName(): string
    {
        return ContentUtility::getBackendUsernameById((int) $this->data['author']);
    }

    public function getTimeAgo(): string
    {
        return DiffUtility::timeAgo($this->data['crdate']);
    }

    public function getEditUri(): string
    {
        return UrlUtility::getEditCommentUrl((int) $this->data['uid']);
    }

    public function getResolvedUri(): string
    {
        return UrlUtility::getResolvedCommentUrl((int) $this->data['uid'], $this->isResolved());
    }

    public function getDeleteUri(): string
    {
        return UrlUtility::getDeleteCommentUrl((int) $this->data['uid']);
    }

    public function isEdited(): bool
    {
        return (bool) $this->data['edited'];
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

        return ContentUtility::getBackendUsernameById((int) $this->data['resolved_user']);
    }

    public function getResolvedDate(): int
    {
        return (int) $this->data['resolved_date'];
    }
}
