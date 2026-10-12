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

use TYPO3\CMS\Backend\Template\Components\Buttons\{DropDownButton, GenericButton};
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\{IconFactory, IconSize};
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\ComponentFactoryUtility;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function sprintf;

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
    private const LLL_BE = 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:';

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
        $languageService = $this->getLanguageService();
        $statusLabel = $languageService->sL(self::LLL_BE.'status');

        if ($isChipMode) {
            $label = $status instanceof Status ? $status->getTitle() : $languageService->sL(self::LLL_BE.'status.set');
            $title = $status instanceof Status ? sprintf($languageService->sL(self::LLL_BE.'status.change'), $status->getTitle()) : $label;
        } else {
            $label = $statusLabel;
            $title = $statusLabel;
        }

        $dropDownButton = ComponentFactoryUtility::createDropDownButton()
            ->setLabel($label)
            ->setShowLabelText($isChipMode)
            ->setTitle($title)
            ->setIcon($this->iconFactory->getIcon(
                $status instanceof Status ? $status->getColoredIcon() : 'flag-gray',
                IconSize::SMALL,
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

        // One group with the status dropdown (docs/design/rules.md, "Doc header group"). The
        // listener calls attachStatusDropdown() right before this, so its group is the last one.
        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $groupKey = array_key_last($buttons['right']);
        $trio = [$this->buildAssigneeButton($table, $uid, $record), $this->buildCommentsButton($table, $uid, $record)];
        if (null !== $groupKey && ($buttons['right'][$groupKey][0] ?? null) instanceof DropDownButton) {
            $buttons['right'][$groupKey] = [...$buttons['right'][$groupKey], ...$trio];
        } else {
            $buttons['right'][] = $trio;
        }
        $event->setButtons($buttons);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function buildAssigneeButton(string $table, int $uid, array $record): GenericButton
    {
        $currentAssignee = (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);
        // Raw name: GenericButton escapes label and attributes itself, the pre-escaped variant would double-encode.
        $username = $currentAssignee > 0 ? $this->backendUserRepository->getDisplayNameByUid($currentAssignee) : '';
        $label = '' !== $username ? $username : $this->getLanguageService()->sL(self::LLL_BE.'header.unassigned');
        $name = $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:button.modal.header.assignee').': '.$label;

        // Shared with the banner (InfoGenerator/HeaderInfo.html) so "assigned to me" cannot
        // drift out of sync between the two headerDisplayMode variants.
        $assignedToCurrentUser = InfoGenerator::getAssignedToCurrentUser($record);

        return GeneralUtility::makeInstance(GenericButton::class)
            ->setTag('button')
            ->setIcon(IconUtility::withInlineMarkup($this->iconFactory->getIcon('content-planner-user-circle', IconSize::SMALL)))
            ->setLabel($label)
            ->setShowLabelText(true)
            ->setTitle($name)
            ->setClasses('content-planner-link--assignee'.($assignedToCurrentUser ? ' content-planner-link--assignee-current' : ''))
            ->setAttributes([
                'type' => 'button',
                'aria-label' => $name,
                'data-id' => (string) $uid,
                'data-table' => $table,
                'data-current-assignee' => (string) $currentAssignee,
                'data-content-planner-assignees' => '1',
                'data-force-ajax-url' => '1',
            ]);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function buildCommentsButton(string $table, int $uid, array $record): GenericButton
    {
        $commentsCount = PlannerUtility::hasComments($record)
            ? $this->commentRepository->countAllByRecord($uid, $table)
            : 0;
        $name = match (true) {
            0 === $commentsCount => $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:button.modal.header.comments'),
            1 === $commentsCount => sprintf($this->getLanguageService()->sL(self::LLL_BE.'comments.count.one'), $commentsCount),
            default => sprintf($this->getLanguageService()->sL(self::LLL_BE.'comments.count.other'), $commentsCount),
        };

        return GeneralUtility::makeInstance(GenericButton::class)
            ->setTag('button')
            ->setIcon(IconUtility::withInlineMarkup($this->iconFactory->getIcon('content-planner-message-circle', IconSize::SMALL)))
            // A count only when there is one: an icon-only button still has its name in aria-label.
            ->setLabel($commentsCount > 0 ? (string) $commentsCount : $name)
            ->setShowLabelText($commentsCount > 0)
            ->setTitle($name)
            ->setClasses('content-planner-link--comments')
            ->setAttributes([
                'type' => 'button',
                'aria-label' => $name,
                'data-id' => (string) $uid,
                'data-table' => $table,
                'data-new-comment-uri' => PermissionUtility::canCreateComment() ? UrlUtility::getNewCommentUrl($table, $uid) : '',
                'data-edit-uri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid),
                'data-content-planner-comments' => '1',
                'data-force-ajax-url' => '1',
                'data-focus-composer' => 'true',
            ]);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
