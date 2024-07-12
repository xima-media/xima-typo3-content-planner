<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\DiffUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

class HistoryItem
{
    public array $data = [];

    public static function create(array $sysHistoryRow): static
    {
        $item = new static();
        $item->data = $sysHistoryRow;
        $item->data['raw_history'] = json_decode($sysHistoryRow['history_data'], true);

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        return ((int)ContentUtility::getExtensionRecord($this->data['tablename'], (int)$this->data['recuid'])['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getPid(): int
    {
        return (int)$this->data['recuid'];
    }

    public function getTitle(): ?string
    {
        return $this->getRelatedRecord()['title'];
    }

    private function getRelatedRecord(): array|bool
    {
        switch ($this->data['tablename']) {
            case 'pages':
                $this->data['relatedRecordTablename'] = 'pages';
                return ContentUtility::getPage((int)$this->data['recuid']);
            case 'tx_ximatypo3contentplanner_comment':
                if ($this->data['raw_history']['foreign_table'] && $this->data['raw_history']['foreign_uid']) {
                    $table = $this->data['raw_history']['foreign_table'];
                    $uid = (int)$this->data['raw_history']['foreign_uid'];
                } else {
                    $comment = ContentUtility::getComment((int)$this->data['recuid']);
                    $table = $comment['foreign_table'];
                    $uid = (int)$comment['foreign_uid'];
                }
                $this->data['relatedRecordTablename'] = $table;

                return ContentUtility::getExtensionRecord($table, $uid);
            default:
                $this->data['relatedRecordTablename'] = $this->data['tablename'];
                return ContentUtility::getExtensionRecord($this->data['tablename'], (int)$this->data['recuid']);
        }
    }

    public function getStatus(): ?string
    {
        return ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status'])?->getTitle();
    }

    public function getStatusIcon(): string
    {
        return ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status'])->getColoredIcon();
    }

    public function getRecordIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $record = $this->getRelatedRecord();
        return $iconFactory->getIconForRecord($this->data['relatedRecordTablename'], $record, Icon::SIZE_SMALL)->getIdentifier();
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
        switch ($this->data['tablename']) {
            case 'tx_ximatypo3contentplanner_comment':
                return 'actions-comment';
            default:
                if (!ExtensionUtility::isRegisteredRecordTable($this->data['tablename'])) {
                    break;
                }
                switch (array_key_first($this->data['raw_history']['newRecord'])) {
                    case 'tx_ximatypo3contentplanner_status':
                        $status = ContentUtility::getStatus((int)$this->data['raw_history']['newRecord']['tx_ximatypo3contentplanner_status']);
                        if (!$status) {
                            return 'flag-gray';
                        }
                        return $status->getColoredIcon();
                    case 'tx_ximatypo3contentplanner_assignee':
                        return 'actions-user';
                }
                break;
        }
        return 'actions-open
';
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
