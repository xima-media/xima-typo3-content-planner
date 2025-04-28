<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\SysFileMetadataRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;

class PageTreeSelectionService extends AbstractSelectionService implements SelectionInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        StatusSelectionManager $statusSelectionManager,
        UriBuilder $uriBuilder,
        SysFileMetadataRepository $sysFileMetadataRepository,
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $sysFileMetadataRepository, $uriBuilder);
    }

    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        if ($this->compareStatus($status, $currentStatus)) {
            return;
        }
        $selectionEntriesToAdd[$status->getUid()] = [
            'label' => $status->getTitle(),
            'iconIdentifier' => $status->getColoredIcon(),
            'callbackAction' => 'change',
        ];
    }

    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        $selectionEntriesToAdd['divider' . ($additionalPostIdentifier ?? '')] = ['type' => 'divider'];
    }

    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        $selectionEntriesToAdd['reset'] = [
            'label' => 'LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:reset',
            'iconIdentifier' => 'actions-close',
            'callbackAction' => 'reset',
        ];
    }

    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $username = $this->backendUserRepository->getUsernameByUid($record['tx_ximatypo3contentplanner_assignee']);
        if ($username === '') {
            return;
        }

        $selectionEntriesToAdd['assignee'] = [
            'label' => $username,
            'iconIdentifier' => 'actions-user',
            'callbackAction' => 'load',
        ];
    }

    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $selectionEntriesToAdd['comments'] = [
            'label' => ($record['tx_ximatypo3contentplanner_comments'] ?  $this->commentRepository->countAllByRecord($record['uid'], $table) . ' ' : '') . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments'),
            'iconIdentifier' => 'actions-message',
            'callbackAction' => 'comments',
        ];
    }

    public function addCommentsTodoItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $todoTotal = $this->getCommentsTodoTotal($record, $table);
        if ($todoTotal === 0) {
            return;
        }

        $todoResolved = $this->getCommentsTodoResolved($record, $table);

        $selectionEntriesToAdd['commentsTodo'] = [
            'label' => "$todoResolved/$todoTotal " . $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments.todo'),
            'iconIdentifier' => 'actions-check-square',
            'callbackAction' => 'comments',
        ];
    }
}
