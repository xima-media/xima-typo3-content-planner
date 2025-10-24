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

use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\{ContentUtility, DiffUtility, ExtensionUtility, IconHelper, PermissionUtility, UrlHelper};

use function array_key_exists;
use function is_string;

/**
 * HistoryItem.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class HistoryItem
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|bool|null */
    public array|bool|null $relatedRecord = [];

    /**
     * @param array<string, mixed> $sysHistoryRow
     */
    public static function create(array $sysHistoryRow): static
    {
        $item = new self();
        $item->data = $sysHistoryRow;
        $item->data['raw_history'] = isset($sysHistoryRow['history_data']) && is_string($sysHistoryRow['history_data']) && '' !== $sysHistoryRow['history_data'] ? json_decode($sysHistoryRow['history_data'], true) : null;

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
            return false;
        }

        $record = ContentUtility::getExtensionRecord($this->data['tablename'], (int) $this->data['recuid']);

        if (null === $record) {
            return false;
        }

        if ('tx_ximatypo3contentplanner_comment' === $this->data['tablename'] && array_key_exists('foreign_table', $this->data['raw_history']) && array_key_exists('foreign_uid', $this->data['raw_history'])) {
            $record = ContentUtility::getExtensionRecord($this->data['raw_history']['foreign_table'], (int) $this->data['raw_history']['foreign_uid']);
        }

        if (null === $record || !array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return false;
        }

        return ((int) $record['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getPid(): int
    {
        return (int) $this->data['recuid'];
    }

    public function getTitle(): string
    {
        return ExtensionUtility::getTitle(ExtensionUtility::getTitleField($this->data['relatedRecordTablename']), $this->getRelatedRecord());
    }

    /**
     * @return array<string, mixed>|bool
     */
    public function getRelatedRecord(): array|bool
    {
        if ([] === $this->relatedRecord || false === $this->relatedRecord) {
            switch ($this->data['tablename']) {
                case 'pages':
                    $this->data['relatedRecordTablename'] = 'pages';
                    $this->relatedRecord = ContentUtility::getPage((int) $this->data['recuid']);
                    break;
                case 'tx_ximatypo3contentplanner_comment':
                    if (
                        null !== $this->data['raw_history']
                        && array_key_exists('foreign_table', $this->data['raw_history'])
                        && array_key_exists('foreign_uid', $this->data['raw_history'])
                        && $this->data['raw_history']['foreign_table']
                        && $this->data['raw_history']['foreign_uid']
                    ) {
                        $table = $this->data['raw_history']['foreign_table'];
                        $uid = (int) $this->data['raw_history']['foreign_uid'];
                    } else {
                        $comment = ContentUtility::getComment((int) $this->data['recuid']);
                        if (!$comment) {
                            return [];
                        }
                        $table = $comment['foreign_table'];
                        $uid = (int) $comment['foreign_uid'];
                    }
                    $this->data['relatedRecordTablename'] = $table;

                    $this->relatedRecord = ContentUtility::getExtensionRecord($table, $uid);
                    break;
                default:
                    $this->data['relatedRecordTablename'] = $this->data['tablename'];
                    $this->relatedRecord = ContentUtility::getExtensionRecord($this->data['tablename'], (int) $this->data['recuid']);
            }
        }

        if (!PermissionUtility::checkAccessForRecord($this->data['tablename'], $this->relatedRecord)) {
            $this->relatedRecord = false;
        }

        return $this->relatedRecord;
    }

    public function getRecordLink(): string
    {
        return UrlHelper::getRecordLink($this->data['relatedRecordTablename'], (int) $this->getRelatedRecord()['uid']);
    }

    public function getStatus(): ?string
    {
        $status = ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);

        return $status?->getTitle();
    }

    public function getStatusIcon(): string
    {
        return IconHelper::getIconByStatusUid((int) $this->getRelatedRecord()['tx_ximatypo3contentplanner_status'], true);
    }

    public function getRecordIcon(): string
    {
        return IconHelper::getIconByRecord($this->data['relatedRecordTablename'], $this->getRelatedRecord(), true);
    }

    public function getTimeAgo(): string
    {
        return DiffUtility::timeAgo($this->data['tstamp']);
    }

    public function getUser(): string
    {
        return isset($this->data['realName']) && is_string($this->data['realName']) && '' !== $this->data['realName'] ? $this->data['realName'].' ('.$this->data['username'].')' : $this->data['username'];
    }

    public function getChangeTypeIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        switch ($this->data['tablename']) {
            case 'tx_ximatypo3contentplanner_comment':
                return IconHelper::getIconByIdentifier('actions-comment');
            default:
                if (!ExtensionUtility::isRegisteredRecordTable($this->data['tablename'])) {
                    break;
                }
                switch (array_key_first($this->data['raw_history']['newRecord'])) {
                    case 'tx_ximatypo3contentplanner_status':
                        return IconHelper::getIconByStatusUid((int) $this->data['raw_history']['newRecord']['tx_ximatypo3contentplanner_status'], true);
                    case 'tx_ximatypo3contentplanner_assignee':
                        return IconHelper::getIconByIdentifier('actions-user');
                }
                break;
        }

        return IconHelper::getIconByIdentifier('actions-open');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRawHistoryData(): ?array
    {
        return isset($this->data['history_data']) && is_string($this->data['history_data']) && '' !== $this->data['history_data'] ? json_decode($this->data['history_data'], true) : null;
    }

    public function getHistoryData(): string|bool
    {
        $data = $this->getRawHistoryData();
        $tablename = $this->data['tablename'];
        $actiontype = (int) $this->data['actiontype'];

        /*
        * ToDo: Add more cases for different actions
        */
        if (ExtensionUtility::isRegisteredRecordTable($tablename) && RecordHistoryStore::ACTION_MODIFY === $actiontype) {
            return DiffUtility::checkRecordDiff($data, $actiontype);
        }

        if ('tx_ximatypo3contentplanner_comment' === $tablename) {
            return DiffUtility::checkCommendDiff($data, $actiontype);
        }

        return false;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
