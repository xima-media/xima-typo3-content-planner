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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;

/**
 * PageTreeSelectionService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class PageTreeSelectionService extends AbstractSelectionService implements SelectionInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        StatusSelectionManager $statusSelectionManager,
        UriBuilder $uriBuilder,
        CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
        FolderStatusRepository $folderStatusRepository,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder, $folderStatusRepository);
    }

    /**
     * @param array<string|int, mixed>       $selectionEntriesToAdd
     * @param array<int>|int|null            $uid
     * @param array<string, mixed>|bool|null $record
     */
    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        if ($this->compareStatus($status, $currentStatus)) {
            return;
        }
        $selectionEntriesToAdd[(string) $status->getUid()] = [
            'label' => $status->getTitle(),
            'iconIdentifier' => $status->getColoredIcon(),
            'callbackAction' => 'change',
        ];
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        $selectionEntriesToAdd['divider'.($additionalPostIdentifier ?? '')] = ['type' => 'divider'];
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<int, int>|int|null       $uid
     * @param array<string, mixed>|bool|null $record
     */
    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        $selectionEntriesToAdd['reset'] = [
            'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:reset',
            'iconIdentifier' => 'actions-close',
            'callbackAction' => 'reset',
        ];
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws Exception
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $username = $this->backendUserRepository->getUsernameByUid((int) $record['tx_ximatypo3contentplanner_assignee']);
        if ('' === $username) {
            return;
        }

        $selectionEntriesToAdd['assignee'] = [
            'label' => $username,
            'iconIdentifier' => 'actions-user',
            'callbackAction' => 'load',
        ];
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws Exception
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $commentsLabel = PlannerUtility::hasComments($record)
            ? $this->commentRepository->countAllByRecord($record['uid'], $table).' '
            : '';

        $selectionEntriesToAdd['comments'] = [
            'label' => $commentsLabel.$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments'),
            'iconIdentifier' => 'actions-message',
            'callbackAction' => 'comments',
        ];
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

        $selectionEntriesToAdd['commentsTodo'] = [
            'label' => "$todoResolved/$todoTotal ".$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments.todo'),
            'iconIdentifier' => 'actions-check-square',
            'callbackAction' => 'comments',
        ];
    }
}
