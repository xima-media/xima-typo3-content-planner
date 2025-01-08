<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\DiffUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;
use Xima\XimaTypo3ContentPlanner\Utility\PermissionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

final class HistoryItem
{
    public array $data = [];
    public array|bool $relatedRecord = [];

    public static function create(array $sysHistoryRow): static
    {
        $item = new HistoryItem();
        $item->data = $sysHistoryRow;
        $item->data['raw_history'] = json_decode($sysHistoryRow['history_data'], true);

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
            return false;
        }

        $record = ContentUtility::getExtensionRecord($this->data['tablename'], (int)$this->data['recuid']);

        if ($record === null) {
            return false;
        }

        if ($this->data['tablename'] === 'tx_ximatypo3contentplanner_comment') {
            $record = ContentUtility::getExtensionRecord($this->data['raw_history']['foreign_table'], (int)$this->data['raw_history']['foreign_uid']);
        }

        if ($record === null || !array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return false;
        }
        return ((int)$record['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getPid(): int
    {
        return (int)$this->data['recuid'];
    }

    public function getTitle(): ?string
    {
        return ExtensionUtility::getTitle(ExtensionUtility::getTitleField($this->data['relatedRecordTablename']), $this->getRelatedRecord());
    }

    public function getRelatedRecord(): array|bool
    {
        if (empty($this->relatedRecord)) {
            switch ($this->data['tablename']) {
                case 'pages':
                    $this->data['relatedRecordTablename'] = 'pages';
                    $this->relatedRecord = ContentUtility::getPage((int)$this->data['recuid']);
                    break;
                case 'tx_ximatypo3contentplanner_comment':
                    if (
                        array_key_exists('foreign_table', $this->data['raw_history'])
                        && array_key_exists('foreign_uid', $this->data['raw_history'])
                        && $this->data['raw_history']['foreign_table']
                        && $this->data['raw_history']['foreign_uid']
                    ) {
                        $table = $this->data['raw_history']['foreign_table'];
                        $uid = (int)$this->data['raw_history']['foreign_uid'];
                    } else {
                        $comment = ContentUtility::getComment((int)$this->data['recuid']);
                        $table = $comment['foreign_table'];
                        $uid = (int)$comment['foreign_uid'];
                    }
                    $this->data['relatedRecordTablename'] = $table;

                    $this->relatedRecord = ContentUtility::getExtensionRecord($table, $uid);
                    break;
                default:
                    $this->data['relatedRecordTablename'] = $this->data['tablename'];
                    $this->relatedRecord = ContentUtility::getExtensionRecord($this->data['tablename'], (int)$this->data['recuid']);
            }
        }

        if (!PermissionUtility::checkAccessForRecord($this->data['tablename'], $this->relatedRecord)) {
            $this->relatedRecord = false;
        }
        return $this->relatedRecord;
    }

    public function getRecordLink(): string
    {
        return UrlHelper::getRecordLink($this->data['relatedRecordTablename'], (int)$this->getRelatedRecord()['uid']);
    }

    public function getStatus(): ?string
    {
        $status = ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);
        return $status?->getTitle();
    }

    public function getStatusIcon(): ?string
    {
        return IconHelper::getIconByStatusUid((int)$this->getRelatedRecord()['tx_ximatypo3contentplanner_status'], true);
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
        return $this->data['realName'] ? $this->data['realName'] . ' (' . $this->data['username'] . ')' : $this->data['username'];
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
                        return IconHelper::getIconByStatusUid((int)$this->data['raw_history']['newRecord']['tx_ximatypo3contentplanner_status'], true);
                    case 'tx_ximatypo3contentplanner_assignee':
                        return IconHelper::getIconByIdentifier('actions-user');
                }
                break;
        }
        return IconHelper::getIconByIdentifier('actions-open');
    }

    public function getRawHistoryData(): array
    {
        return json_decode($this->data['history_data'], true);
    }

    public function getHistoryData(): string|bool
    {
        $data = $this->getRawHistoryData();
        $tablename = $this->data['tablename'];
        $actiontype = (int)$this->data['actiontype'];

        /*
        * ToDo: Add more cases for different actions
        */
        if (ExtensionUtility::isRegisteredRecordTable($tablename) && $actiontype === RecordHistoryStore::ACTION_MODIFY) {
            return DiffUtility::checkRecordDiff($data, $actiontype);
        }

        if ($tablename === 'tx_ximatypo3contentplanner_comment') {
            return $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype);
        }

        return false;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
