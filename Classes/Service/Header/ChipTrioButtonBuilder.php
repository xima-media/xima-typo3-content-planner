<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\Header;

use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\ComponentFactoryUtility;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * ChipTrioButtonBuilder.
 *
 * CP-25 (#324): builds the doc header trio (status dropdown, assignee button, comment
 * button), extracted out of ModifyButtonBarEventListener to keep that listener's cognitive
 * complexity manageable.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class ChipTrioButtonBuilder
{
    public function __construct(
        private IconFactory $iconFactory,
        private BackendUserRepository $backendUserRepository,
        private CommentRepository $commentRepository,
        private PageRenderer $pageRenderer,
    ) {}

    /**
     * @param array<string, \TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface>|false $buttonsToAdd
     */
    public function attachStatusDropdown(ModifyButtonBarEvent $event, ?Status $status, array|false $buttonsToAdd): void
    {
        if (false === $buttonsToAdd) {
            return;
        }

        $isChipMode = !ExtensionUtility::isBannerDisplayModeEnabled();
        $statusLabel = $this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status');

        $dropDownButton = ComponentFactoryUtility::createDropDownButton()
            // CP-25 (#324): in chip mode, the status name is shown as visible label text
            // next to the icon (the "chip" visual) instead of just an icon-only dropdown.
            ->setLabel($isChipMode && $status instanceof Status ? $status->getTitle() : 'Dropdown')
            ->setShowLabelText($isChipMode)
            ->setTitle($isChipMode && $status instanceof Status ? $statusLabel.': '.$status->getTitle() : $statusLabel)
            ->setIcon($this->iconFactory->getIcon(
                $status instanceof Status ? $status->getColoredIcon() : 'flag-gray',
            ));

        foreach ($buttonsToAdd as $buttonToAdd) {
            if (!$buttonToAdd->isValid()) {
                continue;
            }
            $dropDownButton->addItem($buttonToAdd);
        }

        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $buttons['right'][] = [$dropDownButton];
        $event->setButtons($buttons);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function addButtons(ModifyButtonBarEvent $event, string $table, int $uid, array $record): void
    {
        InfoGenerator::loadHeaderAssets($this->pageRenderer);

        $this->addAssigneeButton($event, $table, $uid, $record);
        $this->addCommentsButton($event, $table, $uid, $record);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function addAssigneeButton(ModifyButtonBarEvent $event, string $table, int $uid, array $record): void
    {
        $currentAssignee = (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);
        $username = $currentAssignee > 0 ? $this->backendUserRepository->getUsernameByUid($currentAssignee) : '';
        $label = '' !== $username
            ? $username
            : $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.unassigned');

        // Shared with the banner (InfoGenerator/HeaderInfo.html) so "assigned to me" cannot
        // drift out of sync between the two headerDisplayMode variants.
        $assignedToCurrentUser = InfoGenerator::getAssignedToCurrentUser($record);

        $assigneeButton = GeneralUtility::makeInstance(LinkButton::class)
            ->setIcon($this->iconFactory->getIcon('actions-user'))
            ->setShowLabelText(true)
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.assignee').': '.$label)
            ->setClasses('content-planner-link--assignee'.($assignedToCurrentUser ? ' content-planner-link--assignee-current' : ''))
            ->setDataAttributes([
                'id' => (string) $uid,
                'table' => $table,
                'current-assignee' => (string) $currentAssignee,
                'content-planner-assignees' => '1',
                'force-ajax-url' => '1',
            ])
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));

        $this->addButtonToRightGroup($event, $assigneeButton);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function addCommentsButton(ModifyButtonBarEvent $event, string $table, int $uid, array $record): void
    {
        $commentsCount = PlannerUtility::hasComments($record)
            ? $this->commentRepository->countAllByRecord($uid, $table)
            : 0;
        $commentsLabel = $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments');
        $title = $commentsCount > 0 ? $commentsCount.' '.$commentsLabel : $commentsLabel;

        $commentsButton = GeneralUtility::makeInstance(LinkButton::class)
            ->setIcon($this->iconFactory->getIcon('actions-message'))
            ->setShowLabelText(true)
            ->setTitle($title)
            ->setClasses('content-planner-link--comments')
            ->setDataAttributes([
                'id' => (string) $uid,
                'table' => $table,
                'new-comment-uri' => PermissionUtility::canCreateComment() ? UrlUtility::getNewCommentUrl($table, $uid) : '',
                'edit-uri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid),
                'content-planner-comments' => '1',
                'force-ajax-url' => '1',
            ])
            ->setHref(UrlUtility::getContentStatusPropertiesEditUrl($table, $uid));

        $this->addButtonToRightGroup($event, $commentsButton);
    }

    private function addButtonToRightGroup(ModifyButtonBarEvent $event, LinkButton $button): void
    {
        if (!$button->isValid()) {
            return;
        }

        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $buttons['right'][] = [$button];
        $event->setButtons($buttons);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
