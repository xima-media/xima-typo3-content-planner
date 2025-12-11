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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\Data\{ContentUtility, DiffUtility};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

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
        $item->data['raw_history'] = $item->getRawHistoryData();

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

        if (Configuration::TABLE_COMMENT === $this->data['tablename'] && array_key_exists('foreign_table', $this->data['raw_history']) && array_key_exists('foreign_uid', $this->data['raw_history'])) {
            $record = ContentUtility::getExtensionRecord($this->data['raw_history']['foreign_table'], (int) $this->data['raw_history']['foreign_uid']);
        }

        if (null === $record || !array_key_exists(Configuration::FIELD_ASSIGNEE, $record)) {
            return false;
        }

        return ((int) $record[Configuration::FIELD_ASSIGNEE]) === $GLOBALS['BE_USER']->user['uid'];
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
            $this->loadRelatedRecord();
        }

        if (!PermissionUtility::checkAccessForRecord($this->data['tablename'], $this->relatedRecord)) {
            $this->relatedRecord = false;
        }

        return $this->relatedRecord;
    }

    public function getRecordLink(): string
    {
        return UrlUtility::getRecordLink($this->data['relatedRecordTablename'], (int) $this->getRelatedRecord()['uid']);
    }

    public function getStatus(): ?string
    {
        $status = ContentUtility::getStatus((int) $this->getRelatedRecord()[Configuration::FIELD_STATUS]);

        return $status?->getTitle();
    }

    public function getStatusIcon(): string
    {
        return IconUtility::getIconByStatusUid((int) $this->getRelatedRecord()[Configuration::FIELD_STATUS], true);
    }

    public function getRecordIcon(): string
    {
        return IconUtility::getIconByRecord($this->data['relatedRecordTablename'], $this->getRelatedRecord(), true);
    }

    public function getTimeAgo(): string
    {
        return DiffUtility::timeAgo($this->data['tstamp']);
    }

    public function getUser(): string
    {
        $realName = $this->data['realName'] ?? '';

        if (is_string($realName) && '' !== $realName) {
            return $realName.' ('.$this->data['username'].')';
        }

        return $this->data['username'];
    }

    public function getChangeTypeIcon(): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        switch ($this->data['tablename']) {
            case Configuration::TABLE_COMMENT:
                return IconUtility::getIconByIdentifier('actions-comment');
            default:
                if (!ExtensionUtility::isRegisteredRecordTable($this->data['tablename'])) {
                    break;
                }
                switch (array_key_first($this->data['raw_history']['newRecord'])) {
                    case Configuration::FIELD_STATUS:
                        return IconUtility::getIconByStatusUid((int) $this->data['raw_history']['newRecord'][Configuration::FIELD_STATUS], true);
                    case Configuration::FIELD_ASSIGNEE:
                        return IconUtility::getIconByIdentifier('actions-user');
                }
                break;
        }

        return IconUtility::getIconByIdentifier('actions-open');
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

        if (Configuration::TABLE_COMMENT === $tablename) {
            return DiffUtility::checkCommendDiff($data, $actiontype);
        }

        return false;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function loadRelatedRecord(): void
    {
        match ($this->data['tablename']) {
            'pages' => $this->loadPageRecord(),
            Configuration::TABLE_COMMENT => $this->loadCommentRelatedRecord(),
            default => $this->loadDefaultRecord(),
        };
    }

    private function loadPageRecord(): void
    {
        $this->data['relatedRecordTablename'] = 'pages';
        $this->relatedRecord = ContentUtility::getPage((int) $this->data['recuid']);
    }

    private function loadCommentRelatedRecord(): void
    {
        $foreignData = $this->resolveForeignDataFromComment();
        if (null === $foreignData) {
            $this->relatedRecord = [];

            return;
        }

        $this->data['relatedRecordTablename'] = $foreignData['table'];
        $this->relatedRecord = ContentUtility::getExtensionRecord($foreignData['table'], $foreignData['uid']);
    }

    private function loadDefaultRecord(): void
    {
        $this->data['relatedRecordTablename'] = $this->data['tablename'];
        $this->relatedRecord = ContentUtility::getExtensionRecord($this->data['tablename'], (int) $this->data['recuid']);
    }

    /**
     * @return array{table: string, uid: int}|null
     *
     * @throws Exception
     */
    private function resolveForeignDataFromComment(): ?array
    {
        if (
            null !== $this->data['raw_history']
            && array_key_exists('foreign_table', $this->data['raw_history'])
            && array_key_exists('foreign_uid', $this->data['raw_history'])
            && $this->data['raw_history']['foreign_table']
            && $this->data['raw_history']['foreign_uid']
        ) {
            return [
                'table' => $this->data['raw_history']['foreign_table'],
                'uid' => (int) $this->data['raw_history']['foreign_uid'],
            ];
        }

        $comment = ContentUtility::getComment((int) $this->data['recuid']);
        if (!$comment) {
            return null;
        }

        return [
            'table' => $comment['foreign_table'],
            'uid' => (int) $comment['foreign_uid'],
        ];
    }
}
