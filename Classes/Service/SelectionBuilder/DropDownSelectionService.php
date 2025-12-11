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

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\ComponentFactoryUtility;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

/**
 * DropDownSelectionService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class DropDownSelectionService extends AbstractSelectionService implements SelectionInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        StatusSelectionManager $statusSelectionManager,
        UriBuilder $uriBuilder,
        CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly IconFactory $iconFactory,
        FolderStatusRepository $folderStatusRepository,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder, $folderStatusRepository);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     */
    public function addHeaderItemToSelection(array &$selectionEntriesToAdd): void
    {
        $title = $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:status');

        $headerItem = ComponentFactoryUtility::createDropDownHeader()
            ->setLabel($title);
        $selectionEntriesToAdd['header'] = $headerItem;
        $selectionEntriesToAdd['headerDivider'] = ComponentFactoryUtility::createDropDownDivider();
    }

    /**
     * @param array<string|int, mixed>       $selectionEntriesToAdd
     * @param array<int>|int|null            $uid
     * @param array<string, mixed>|bool|null $record
     *
     * @throws RouteNotFoundException
     */
    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        if ($this->compareStatus($status, $currentStatus)) {
            return;
        }
        $statusDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($status->getTitle())
            ->setIcon($this->iconFactory->getIcon($status->getColoredIcon()))
            ->setAttributes(['data-content-planner-status-change' => 'true'])
            ->setHref($this->buildUriForStatusChange($table, $uid, $status)->__toString());
        $selectionEntriesToAdd[(string) $status->getUid()] = $statusDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        $selectionEntriesToAdd['divider'.($additionalPostIdentifier ?? '')] = ComponentFactoryUtility::createDropDownDivider();
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<int, int>|int|null       $uid
     * @param array<string, mixed>|bool|null $record
     *
     * @throws RouteNotFoundException
     */
    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        $statusDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'))
            ->setIcon($this->iconFactory->getIcon('actions-close'))
            ->setAttributes(['data-content-planner-status-change' => 'true', 'data-content-planner-status-reset' => 'true'])
            ->setHref($this->buildUriForStatusChange($table, $uid, null)->__toString());
        $selectionEntriesToAdd['reset'] = $statusDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $currentAssignee = (int) $record[Configuration::FIELD_ASSIGNEE];
        $username = $this->backendUserRepository->getUsernameByUid($currentAssignee);
        $label = '' !== $username
            ? $username
            : $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.unassigned');

        $assigneeDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($label)
            ->setIcon($this->iconFactory->getIcon('actions-user'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-current-assignee' => $currentAssignee, 'data-content-planner-assignees' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['assignee'] = $assigneeDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $commentsLabel = PlannerUtility::hasComments($record)
            ? $this->commentRepository->countAllByRecord($record['uid'], $table).' '
            : '';

        $commentsDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($commentsLabel.$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments'))
            ->setIcon($this->iconFactory->getIcon('actions-message'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-new-comment-uri' => UrlUtility::getNewCommentUrl($table, $uid), 'data-edit-uri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid), 'data-content-planner-comments' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['comments'] = $commentsDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws RouteNotFoundException
     */
    public function addCommentsTodoItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $todoTotal = $this->getCommentsTodoTotal($record, $table);
        if (0 === $todoTotal) {
            return;
        }

        $todoResolved = $this->getCommentsTodoResolved($record, $table);
        $commentsDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel("$todoResolved/$todoTotal ".$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments.todo'))
            ->setIcon($this->iconFactory->getIcon('actions-check-square'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-new-comment-uri' => UrlUtility::getNewCommentUrl($table, $uid), 'data-edit-uri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid), 'data-content-planner-comments' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['commentsTodo'] = $commentsDropDownItem;
    }

    /**
     * @param array<int|string, mixed> $selectionEntriesToAdd
     *
     * @throws RouteNotFoundException
     */
    public function addFolderStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, ?int $currentStatus, string $combinedIdentifier): void
    {
        if (null !== $currentStatus && $status->getUid() === $currentStatus) {
            return;
        }

        $statusDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($status->getTitle())
            ->setIcon($this->iconFactory->getIcon($status->getColoredIcon()))
            ->setAttributes(['data-content-planner-status-change' => 'true'])
            ->setHref($this->buildUriForFolderStatusChange($combinedIdentifier, $status)->__toString());
        $selectionEntriesToAdd[(string) $status->getUid()] = $statusDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     *
     * @throws RouteNotFoundException
     */
    public function addFolderStatusResetItemToSelection(array &$selectionEntriesToAdd, string $combinedIdentifier): void
    {
        $statusDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'))
            ->setIcon($this->iconFactory->getIcon('actions-close'))
            ->setAttributes(['data-content-planner-status-change' => 'true', 'data-content-planner-status-reset' => 'true'])
            ->setHref($this->buildUriForFolderStatusChange($combinedIdentifier, null)->__toString());
        $selectionEntriesToAdd['reset'] = $statusDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $folderRecord
     *
     * @throws RouteNotFoundException|Exception
     */
    public function addFolderAssigneeItemToSelection(array &$selectionEntriesToAdd, array $folderRecord, string $combinedIdentifier): void
    {
        $table = Configuration::TABLE_FOLDER;
        $uid = (int) $folderRecord['uid'];
        $currentAssignee = (int) ($folderRecord[Configuration::FIELD_ASSIGNEE] ?? 0);

        $username = $this->backendUserRepository->getUsernameByUid($currentAssignee);
        $label = '' !== $username
            ? $username
            : $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.unassigned');

        $assigneeDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($label)
            ->setIcon($this->iconFactory->getIcon('actions-user'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-current-assignee' => $currentAssignee, 'data-content-planner-assignees' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['assignee'] = $assigneeDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $folderRecord
     *
     * @throws RouteNotFoundException|Exception
     */
    public function addFolderCommentsItemToSelection(array &$selectionEntriesToAdd, array $folderRecord, string $combinedIdentifier): void
    {
        $table = Configuration::TABLE_FOLDER;
        $uid = (int) $folderRecord['uid'];

        $commentsLabel = PlannerUtility::hasComments($folderRecord)
            ? $this->commentRepository->countAllByRecord($uid, $table).' '
            : '';

        $commentsDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($commentsLabel.$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments'))
            ->setIcon($this->iconFactory->getIcon('actions-message'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-new-comment-uri' => UrlUtility::getNewCommentUrl($table, $uid), 'data-edit-uri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid), 'data-content-planner-comments' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['comments'] = $commentsDropDownItem;
    }
}
