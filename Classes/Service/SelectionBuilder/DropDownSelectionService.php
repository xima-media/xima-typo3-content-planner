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

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\ComponentFactoryUtility;
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
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly IconFactory $iconFactory,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder);
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
            ->setHref($this->buildUriForStatusChange($table, $uid, null)->__toString());
        $selectionEntriesToAdd['reset'] = $statusDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $username = $this->backendUserRepository->getUsernameByUid((int) $record['tx_ximatypo3contentplanner_assignee']);
        if ('' === $username) {
            return;
        }

        $assigneeDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel($username)
            ->setIcon($this->iconFactory->getIcon('actions-user'))
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['assignee'] = $assigneeDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $commentsDropDownItem = ComponentFactoryUtility::createDropDownItem()
            ->setLabel((isset($record['tx_ximatypo3contentplanner_comments']) && is_numeric($record['tx_ximatypo3contentplanner_comments']) && $record['tx_ximatypo3contentplanner_comments'] > 0 ? $this->commentRepository->countAllByRecord($record['uid'], $table).' ' : '').$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments'))
            ->setIcon($this->iconFactory->getIcon('actions-message'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-new-comment-uri' => UrlUtility::getNewCommentUrl($table, $uid), 'data-edit-uri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid), 'data-content-planner-comments' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['comments'] = $commentsDropDownItem;
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
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
}
