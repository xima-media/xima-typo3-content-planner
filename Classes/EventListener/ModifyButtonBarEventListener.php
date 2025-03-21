<?php

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\InputButton;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\HeaderSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

final class ModifyButtonBarEventListener
{
    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly HeaderSelectionService $buttonSelectionService,
    ) {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];

        if ($request->getAttribute('module') &&
            !in_array($request->getAttribute('module')->getIdentifier(), ['web_layout', 'record_edit', 'web_list'])) {
            return;
        }

        if (isset($request->getQueryParams()['edit'])) {
            $table = array_key_first($request->getQueryParams()['edit']);
        } elseif (isset($request->getQueryParams()['id'])) {
            $table = 'pages';
        } else {
            return;
        }

        if ($table === 'tx_ximatypo3contentplanner_comment') {
            $this->removeButtonsExceptSave($event);
            return;
        }
        if (!ExtensionUtility::isRegisteredRecordTable($table)) {
            return;
        }

        if ($table === 'pages') {
            $uid = (int)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? (isset($request->getQueryParams()['edit']['pages']) ? array_keys($request->getQueryParams()['edit']['pages'])[0] : 0));
        } else {
            $uid = (int)array_key_first($request->getQueryParams()['edit'][$table]);
        }
        $record = $this->recordRepository->findByUid($table, $uid, ignoreHiddenRestriction: true);

        if (!$record) {
            return;
        }
        $status = $record['tx_ximatypo3contentplanner_status'] ? $this->statusRepository->findByUid($record['tx_ximatypo3contentplanner_status']) : null;

        $buttonBar = $event->getButtonBar();
        $buttons = $event->getButtons();
        $buttons['right'] ??= [];
        $dropDownButton = $buttonBar->makeDropDownButton()
            ->setLabel('Dropdown')
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:status'))
            ->setIcon($this->iconFactory->getIcon(
                $status ? $status->getColoredIcon() : 'flag-gray'
            ));

        $buttonsToAdd = $this->buttonSelectionService->generateSelection($table, $uid);
        if ($buttonsToAdd === false) {
            return;
        }

        foreach ($buttonsToAdd as $buttonToAdd) {
            $dropDownButton->addItem($buttonToAdd);
        }

        $buttons['right'][] = [$dropDownButton];
        $event->setButtons($buttons);
    }

    private function removeButtonsExceptSave(ModifyButtonBarEvent $event): void
    {
        $buttons = [];

        foreach ($event->getButtons() as $position => $buttonGroup) {
            if ($position === 'right') {
                continue;
            }
            foreach ($buttonGroup as $button) {
                if ($button[0] instanceof InputButton && str_contains($button[0]->getName(), '_save')) {
                    $button[0]->setTitle($this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:save_and_close'));

                    $buttons[$position][] = $button;
                }
            }
        }
        $event->setButtons($buttons);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
