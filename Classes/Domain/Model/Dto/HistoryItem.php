<?php

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\DiffUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\PermissionUtility;

final class HistoryItem
{
    public array $data = [];
    public array|bool $relatedRecord = [];
    public bool $cliContext = false;

    public static function create(array $sysHistoryRow, bool $cliContext = false): static
    {
        $item = new HistoryItem();
        $item->data = $sysHistoryRow;
        $item->data['raw_history'] = json_decode($sysHistoryRow['history_data'], true);
        $item->cliContext = $cliContext;

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        $record = ContentUtility::getExtensionRecord($this->data['tablename'], (int)$this->data['recuid']);

        if ($record === false || !array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
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
        $titleField = $GLOBALS['TCA'][$this->data['relatedRecordTablename']]['ctrl']['label'];
        return array_key_exists($titleField, $this->getRelatedRecord()) ? $this->getRelatedRecord()[$titleField] : $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title');
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
                    if ($this->data['raw_history']['foreign_table'] && $this->data['raw_history']['foreign_uid']) {
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
        $record = $this->getRelatedRecord();
        switch ($this->data['relatedRecordTablename']) {
            case 'pages':
                return (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $record['uid']]);
            default:
                return (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', ['edit' => [$this->data['relatedRecordTablename'] => [$record['uid'] => 'edit']]]);
        }
    }

    public function getStatus(): ?string
    {
        $status = ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);
        return $status?->getTitle();
    }

    public function getStatusIcon(): ?string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $status = ContentUtility::getStatus($this->getRelatedRecord()['tx_ximatypo3contentplanner_status']);
        return $this->renderIcon($iconFactory->getIcon($status ? $status->getColoredIcon() : 'flag-gray', Icon::SIZE_SMALL));
    }

    public function getRecordIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $record = $this->getRelatedRecord();
        return $record ? $this->renderIcon($iconFactory->getIconForRecord($this->data['relatedRecordTablename'], $record, Icon::SIZE_SMALL)) : '';
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
                return $this->renderIcon($iconFactory->getIcon('actions-comment', Icon::SIZE_SMALL));
            default:
                if (!ExtensionUtility::isRegisteredRecordTable($this->data['tablename'])) {
                    break;
                }
                switch (array_key_first($this->data['raw_history']['newRecord'])) {
                    case 'tx_ximatypo3contentplanner_status':
                        $status = ContentUtility::getStatus((int)$this->data['raw_history']['newRecord']['tx_ximatypo3contentplanner_status']);
                        if (!$status) {
                            return $this->renderIcon($iconFactory->getIcon('flag-gray', Icon::SIZE_SMALL));
                        }
                        return $this->renderIcon($iconFactory->getIcon($status->getColoredIcon(), Icon::SIZE_SMALL));
                    case 'tx_ximatypo3contentplanner_assignee':
                        return $this->renderIcon($iconFactory->getIcon('actions-user', Icon::SIZE_SMALL));
                }
                break;
        }
        return $this->renderIcon($iconFactory->getIcon('actions-open', Icon::SIZE_SMALL));
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

    protected function renderIcon(Icon $icon): string
    {
        return $this->cliContext ? $icon->getAlternativeMarkup('inline') : $icon->render();
    }
}
