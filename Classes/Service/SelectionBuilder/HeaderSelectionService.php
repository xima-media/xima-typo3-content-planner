<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownDivider;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItem;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

class HeaderSelectionService extends AbstractSelectionService implements SelectionInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        StatusSelectionManager $statusSelectionManager,
        CommentRepository $commentRepository,
        UriBuilder $uriBuilder,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly IconFactory $iconFactory,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder);
    }

    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        if ($this->compareStatus($status, $currentStatus)) {
            return;
        }
        /** @var DropDownItemInterface $statusDropDownItem */
        $statusDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
            ->setLabel($status->getTitle())
            ->setIcon($this->iconFactory->getIcon($status->getColoredIcon()))
            ->setHref($this->buildUriForStatusChange($table, $uid, $status)->__toString());
        $selectionEntriesToAdd[$status->getUid()] = $statusDropDownItem;
    }

    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        $selectionEntriesToAdd['divider' . ($additionalPostIdentifier ?? '')] = GeneralUtility::makeInstance(DropDownDivider::class);
    }

    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        /** @var DropDownItemInterface $statusDropDownItem */
        $statusDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
            ->setLabel($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'))
            ->setIcon($this->iconFactory->getIcon('actions-close'))
            ->setHref($this->buildUriForStatusChange($table, $uid, null)->__toString());
        $selectionEntriesToAdd['reset'] = $statusDropDownItem;
    }

    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $username = $this->backendUserRepository->getUsernameByUid($record['tx_ximatypo3contentplanner_assignee']);
        if ($username === '') {
            return;
        }

        $assigneeDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
            ->setLabel($username)
            ->setIcon($this->iconFactory->getIcon('actions-user'))
            ->setHref(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['assignee'] = $assigneeDropDownItem;
    }

    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $commentsDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
            ->setLabel(($record['tx_ximatypo3contentplanner_comments'] ?  $record['tx_ximatypo3contentplanner_comments'] . ' ' : '') . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments'))
            ->setIcon($this->iconFactory->getIcon('actions-message'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-new-comment-uri' => UrlHelper::getNewCommentUrl($table, $uid), 'data-edit-uri' => UrlHelper::getContentStatusPropertiesEditUrl($table, $uid), 'data-content-planner-comments' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['comments'] = $commentsDropDownItem;
    }

    public function addCommentsTodoItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $todoTotal = $this->getCommentsTodoTotal($record, $table);
        if ($todoTotal === 0) {
            return;
        }

        $todoResolved = $this->getCommentsTodoResolved($record, $table);
        $commentsDropDownItem = GeneralUtility::makeInstance(DropDownItem::class)
            ->setLabel("$todoResolved/$todoTotal " . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments.todo'))
            ->setIcon($this->iconFactory->getIcon('actions-check-square'))
            ->setAttributes(['data-id' => $uid, 'data-table' => $table, 'data-new-comment-uri' => UrlHelper::getNewCommentUrl($table, $uid), 'data-edit-uri' => UrlHelper::getContentStatusPropertiesEditUrl($table, $uid), 'data-content-planner-comments' => true, 'data-force-ajax-url' => true]) // @phpstan-ignore-line
            ->setHref(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid));
        $selectionEntriesToAdd['commentsTodo'] = $commentsDropDownItem;
    }
}
