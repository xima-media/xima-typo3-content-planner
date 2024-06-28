<?php

namespace Xima\XimaContentPlanner\Domain\Model\Dto;

use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaContentPlanner\Configuration;
use Xima\XimaContentPlanner\Utility\ContentUtility;

class HistoryItem
{
    public array $data = [];

    public static function create(array $sysHistoryRow): static
    {
        $item = new static();
        $item->data = $sysHistoryRow;
        $item->data['raw_history'] = json_decode($sysHistoryRow['history_data'], true);
        $item->data['recuid'] = $item->data['tablename'] === 'tx_ximacontentplanner_comment' ?  $item->data['raw_history']['pid'] : $item->data['recuid'];

        return $item;
    }

    public function getAssignedToCurrentUser(): bool
    {
        return ((int)ContentUtility::getPage((int)$this->data['recuid'])['tx_ximacontentplanner_assignee']) === $GLOBALS['BE_USER']->user['uid'];
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
        return ContentUtility::getPage((int)$this->data['recuid'])['tx_ximacontentplanner_status'];
    }

    public function getPageStatusIcon(): string
    {
        return Configuration::STATUS_ICONS[ContentUtility::getPage((int)$this->data['recuid'])['tx_ximacontentplanner_status']];
    }

    public function getRawHistoryData(): array
    {
        return json_decode($this->data['history_data'], true);
    }

    public function getHistoryData(): string
    {
        $data = $this->getRawHistoryData();
        $tablename = $this->data['tablename'];
        $actiontype = (int)$this->data['actiontype'];

        /*
         * ToDo: Add more cases for different actions
         */
        if ($tablename === 'pages' && $actiontype === RecordHistoryStore::ACTION_MODIFY) {
            return $this->checkDiff($data, $actiontype);
        }

        if ($tablename === 'tx_ximacontentplanner_comment') {
            return $this->getLanguageService()->sL('LLL:EXT:xima_content_planner/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype);
        }

        return '';
    }

    private function checkDiff(array $data, int $actiontype): string
    {
        $old = $data['oldRecord'];
        $new = $data['newRecord'];
        $diff = [];

        foreach ($old as $key => $value) {
            if ($key !== 'l10n_diffsource') {
                $oldValue = $value;
                $newValue = $new[$key];
                if ($oldValue !== $newValue) {
                    $diff[] = $this->makeDiffReadable($key, $actiontype, $oldValue, $newValue);
                }
            }
        }
        return implode('<br/>', $diff);
    }

    private function makeDiffReadable(string $field, int $actiontype, string|int|null $old, string|int|null $new)
    {
        // set value
        if (($old === null || $old === '') && ($new === null && $new === '')) {
            return '';
        }
        if ($old === null || $old === '') {
            return sprintf($this->getLanguageService()->sL('LLL:EXT:xima_content_planner/Resources/Private/Language/locallang_be.xlf:history.pages.' . $actiontype . '.set.' . $field), $this->prepareValue($field, $new));
        }
        // reset value
        if ($new === null || $new === '') {
            return sprintf($this->getLanguageService()->sL('LLL:EXT:xima_content_planner/Resources/Private/Language/locallang_be.xlf:history.pages.' . $actiontype . '.unset.' . $field), $this->prepareValue($field, $old));
        }
        // change value
        if ($old !== $new) {
            return sprintf($this->getLanguageService()->sL('LLL:EXT:xima_content_planner/Resources/Private/Language/locallang_be.xlf:history.pages.' . $actiontype . '.change.' . $field), $this->prepareValue($field, $old), $this->prepareValue($field, $new));
        }
        return '';
    }

    private function prepareValue(string $field, string|int $value): string
    {
        switch ($field) {
            case 'tx_ximacontentplanner_status':
                return $this->getLanguageService()->sL('LLL:EXT:xima_content_planner/Resources/Private/Language/locallang_be.xlf:status.' . $value);
            case 'tx_ximacontentplanner_assignee':
                return ContentUtility::getBackendUsernameById((int)$value);
        }
        return $value;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
