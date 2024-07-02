<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\DiffUtility;

class HistoryItem
{
    public array $data = [];

    public static function create(array $sysHistoryRow): static
    {
        $item = new static();
        $item->data = $sysHistoryRow;
        $item->data['raw_history'] = json_decode($sysHistoryRow['history_data'], true);
        $item->data['recuid'] = $item->data['tablename'] === 'tx_ximatypo3contentplanner_comment' ?  $item->data['raw_history']['pid'] : $item->data['recuid'];

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        return ((int)ContentUtility::getPage((int)$this->data['recuid'])['tx_ximatypo3contentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
    }

    public function getPid(): int
    {
        return (int)$this->data['recuid'];
    }

    public function getPageTitle(): ?string
    {
        return ContentUtility::getPage((int)$this->data['recuid'])['title'];
    }

    public function getPageStatus(): ?string
    {
        return ContentUtility::getPage((int)$this->data['recuid'])['tx_ximatypo3contentplanner_status'];
    }

    public function getPageStatusIcon(): string
    {
        return Configuration::STATUS_ICONS[ContentUtility::getPage((int)$this->data['recuid'])['tx_ximatypo3contentplanner_status']];
    }

    public function getTimeAgo(): string
    {
        $x = DiffUtility::timeAgo($this->data['tstamp']);
        return DiffUtility::timeAgo($this->data['tstamp']);
    }

    public function getUser(): string
    {
        return $this->data['realName'] ? $this->data['realName'] . ' (' . $this->data['username'] . ')' : $this->data['username'];
    }

    public function getChangeTypeIcon(): string
    {
        switch ($this->data['tablename']) {
            case 'pages':
                switch (array_key_first($this->data['raw_history']['newRecord'])) {
                    case 'tx_ximatypo3contentplanner_status':
                        return Configuration::STATUS_ICONS[$this->data['raw_history']['newRecord']['tx_ximatypo3contentplanner_status']];
                    case 'tx_ximatypo3contentplanner_assignee':
                        return 'actions-user';
                }
            case 'tx_ximatypo3contentplanner_comment':
                return 'actions-comment';
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
        if ($tablename === 'pages' && $actiontype === RecordHistoryStore::ACTION_MODIFY) {
            return DiffUtility::checkPagesDiff($data, $actiontype);
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
